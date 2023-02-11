<?php
declare(strict_types=1);

namespace Gitplus\PayFluid;

use DateTime;
use Exception;
use InvalidArgumentException;
use phpseclib\Crypt\RSA;
use Throwable;

class PayFluid
{
    /**
     * The API id obtained from PayFluid
     *
     * @var string
     */
    protected string $apiId;

    /**
     * The API key obtained from PayFluid
     *
     * @var string
     */
    protected string $apiKey;

    /**
     * The login parameter obtained from PayFluid
     * @var string
     */
    protected string $loginParameter;

    private const TEST_BASE_URL = "https://payfluid-api.herokuapp.com/payfluid/ext/api";
    private const LIVE_BASE_URL = "https://payfluid-api.herokuapp.com/payfluid/ext/api";

    /**
     * Specifies the maximum number of characters in a payment description
     *
     * @var string
     */
    private const MAX_DESCRIPTION_LEN = 40;


    /**
     * The various endpoints provided by the API
     *
     * @var array
     */
    protected array $endpoints = [
        "test" => [
            "secureZone" => self::TEST_BASE_URL . "/secureCredentials",
            "getPaymentLink" => self::TEST_BASE_URL . "/getPayLink",
            "paymentStatus" => "https://www.payoutlet.com.gh/payfluid/ext/api/status?msg",
        ],
        "live" => [
            "secureZone" => self::LIVE_BASE_URL . "/secureCredentials",
            "getPaymentLink" => self::LIVE_BASE_URL . "/getPayLink",
            "paymentStatus" => "https://www.payoutlet.com.gh/payfluid/ext/api/status?msg",
        ]
    ];

    protected bool $inLiveMode;

    /**
     * Instantiates a new PayFluid client.
     *
     * @param string $apiId The API id supplied from PayFluid
     * @param string $apiKey The API key supplied from PayFluid
     * @param string $loginParameter The login parameter supplied from PayFluid
     * @param bool $inLiveMode Indicates whether you are in live mode or test mode; true for live mode, false for test mode
     */
    public function __construct(string $apiId, string $apiKey, string $loginParameter, bool $inLiveMode)
    {
        $this->apiId = $apiId;
        $this->apiKey = $apiKey;
        $this->loginParameter = $loginParameter;
        $this->inLiveMode = $inLiveMode;
    }

    private function getEndpoint(string $endpoint)
    {
        $mode = $this->inLiveMode ? "live" : "test";
        return $this->endpoints[$mode][$endpoint];
    }

    /**
     * Generates an id for use as the API key in the header when getting secure
     * credentials.
     *
     * @param DateTime $now
     * @return string
     */
    private function generateId(DateTime $now): string
    {
        $rsa = new RSA();
        $rsa->loadKey($this->apiKey);
        $rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
        $data = sprintf("%s.%s", $this->loginParameter, $now->format('YmdHisv'));
        $output = $rsa->encrypt($data);
        return base64_encode($output);
    }

    /**
     * Makes a request to the secure zone endpoint to get new secure credentials.
     *
     * @param string $phoneNumber
     * @return SecureCredentials
     * @throws Exception
     */
    public function getSecureCredentials(string $phoneNumber): SecureCredentials
    {
        if (empty($phoneNumber)) {
            throw new Exception("get secure credentials: empty phone number supplied");
        }

        $now = new DateTime();
        $requestBody = json_encode([
            "cmd" => "getSecureParams",
            "datetime" => $now->format('YmdHisv'),
            "mobile" => $phoneNumber,
        ]);

        if ($requestBody === false) {
            throw new Exception("get secure credentials: encoding the request body to json failed: " . json_last_error_msg());
        }

        $rsaPublicKey = $sha256Salt = "";

        $ch = curl_init($this->getEndpoint("secureZone"));
        $optionsSet = curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "id: " . base64_encode($this->apiId),
                "apiKey: " . $this->generateId($now),
            ],

            // This curl option calls the function for each header in the response.
            // This function iterates over each http response header looking for a specific
            // header in the form: "Kek: rsa_public_key.sha_256_salt".
            // We want to find this header and split it to get the rsa public key and sha 256 salt.
            CURLOPT_HEADERFUNCTION => function ($curl, $currentHeader) use (&$rsaPublicKey, &$sha256Salt) {
                $headerLen = strlen($currentHeader);
                if (stripos($currentHeader, "kek") === false) {
                    return $headerLen;
                }

                $kekValue = explode(":", trim($currentHeader))[1];
                $splitKekValue = explode(".", $kekValue);
                $rsaPublicKey = $splitKekValue[0];
                $sha256Salt = $splitKekValue[1];
                return $headerLen;
            }
        ]);
        if (!$optionsSet) {
            throw new Exception("get secure credentials: setting curl options failed: " . curl_error($ch));
        }

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("get secure credentials: http request failed: " . curl_error($ch));
        }

        $response = json_decode($response, false);
        if ($response === null) {
            throw new Exception("get secure credentials: decoding json response failed: " . json_last_error_msg());
        }

        if ($response->resultCode !== "00") {
            throw new Exception("get secure credentials: " . $response->resultMessage);
        }

        return new SecureCredentials(
            $response->session,
            $rsaPublicKey,
            $sha256Salt,
            $response->kekExpiry,
            $response->macExpiry,
            $response->approvalCode
        );
    }

    /**
     * Creates a signature for use in requests to the server
     *
     * @param SecureCredentials $credentials
     * @param array $requestBody
     * @return string A base64 encoded string of the signature
     * @throws Exception
     */
    private function createSignature(SecureCredentials $credentials, array $requestBody): string
    {
        $requestBodyAsString = join("", array_values($requestBody));
        $hash = hash_hmac("sha256", $requestBodyAsString, $credentials->sha256Salt);

        $rsa = new RSA();
        $keyLoaded = $rsa->loadKey($credentials->rsaPublicKey);
        if (!$keyLoaded) {
            throw new Exception("create signature: loading rsa public key failed");
        }

        $rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
        return base64_encode($rsa->encrypt($hash));
    }

    /**
     * Checks to ensure that the payment object is valid
     *
     * @param Payment $payment
     * @throws InvalidPaymentObjectException
     */
    private function validatePaymentObject(Payment $payment)
    {
        // Validate amount
        if (empty($payment->getAmount())) {
            throw new InvalidPaymentObjectException("validate payment: amount cannot be empty");
        }

        // Validate currency
        if (empty($payment->currency())) {
            throw new InvalidPaymentObjectException("validate payment: currency cannot be empty");
        }

        // Validate date time
        if (empty($payment->getDateTime())) {
            throw new InvalidPaymentObjectException("validate payment: datetime cannot be empty: you must supply a date time string in the format 'Y-m-d\TH:i:s.v\Z'");
        }

        // Validate email
        if (empty($payment->getEmail())) {
            throw new InvalidPaymentObjectException("validate payment: email cannot be empty");
        }

        // Validate phone number
        if (empty($payment->getPhone())) {
            throw new InvalidPaymentObjectException("validate payment: phone cannot be empty");
        }

        // Validate reference
        if (empty($payment->getReference())) {
            throw new InvalidPaymentObjectException("validate payment: reference cannot be empty");
        }

        // Validate redirect and callback urls
        if (empty($payment->getRedirectUrl())) {
            throw new InvalidPaymentObjectException("validate payment: redirect url cannot be empty");
        }
    }

    /**
     * Makes a request to PayFluid to initiate a payment.
     *
     * @param SecureCredentials $credentials
     * @param Payment $payment
     * @return PaymentLink A PaymentLink object with a url to the payment page
     * @throws Exception
     */
    public function getPaymentLink(SecureCredentials $credentials, Payment $payment): PaymentLink
    {
        if (empty($credentials->session)) {
            throw new Exception("get payment link: invalid credentials: session value is empty");
        }

        try {
            $this->validatePaymentObject($payment);
        } catch (Throwable $e) {
            throw new InvalidPaymentObjectException("get payment link: invalid payment object: " . $e->getMessage());
        }

        $requestBody = [
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'datetime' => $payment->getDateTime(),
            'email' => $payment->getEmail(),
            'lang' => $payment->getLang(),
            'mobile' => $payment->getPhone(),
            'name' => $payment->getName(),
            'reference' => $payment->getReference(),
            'responseRedirectURL' => $payment->getRedirectUrl(),
            'session' => $credentials->session,
        ];

        if (!empty($payment->getDescription())) {
            $requestBody["descr"] = $payment->getDescription();
        }
        if (!empty($payment->getOtherInfo())) {
            $requestBody["otherInfo"] = $payment->getOtherInfo();
        }
        if (!empty($payment->getCallbackUrl())) {
            $requestBody["trxStatusCallbackURL"] = $payment->getCallbackUrl();
        }
        if ($payment->hasCustomization()) {
            $customTxn = $payment->customization()->getRaw();
            krsort($customTxn, SORT_ASC);
            $requestBody["customTxn"] = $customTxn;
        }

        ksort($requestBody, SORT_ASC);
        $signature = $this->createSignature($credentials, $requestBody);

        $requestBody = json_encode($requestBody, JSON_PRESERVE_ZERO_FRACTION);
        if ($requestBody === false) {
            throw new Exception("get payment link: error encoding request body to json: " . json_last_error_msg());
        }


        $ch = curl_init($this->getEndpoint("getPaymentLink"));
        $optionsSet = curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "signature: " . $signature,
            ],
            CURLOPT_POSTFIELDS => $requestBody
        ]);
        if (!$optionsSet) {
            throw new Exception("get payment link: setting curl options failed: " . curl_error($ch));
        }

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("get payment link: request failed: " . curl_error($ch));
        }

        $result = json_decode($response);
        if ($result === null) {
            throw new Exception(
                sprintf(
                    "get payment link: could not decode json upstream server response '%s': json: %s",
                    $response,
                    json_last_error_msg()
                )
            );
        }

        if ($result->result_code !== "00") {
            throw new Exception(sprintf("get payment link: request failed with code [%d] and message [%s]", $result->result_code, $result->result_message));
        }

        $paymentLink = new PaymentLink();
        $paymentLink->approvalCode = $result->approvalCode;
        $paymentLink->resultMessage = $result->result_message;
        $paymentLink->session = $result->session;
        $paymentLink->webUrl = $result->webURL;
        $paymentLink->resultCode = $result->result_code;

        $payReference = explode("/", $result->webURL);
        $paymentLink->payReference = $payReference[count($payReference) - 1];
        return $paymentLink;
    }


    /**
     * Retrieves and lets you confirm the status of a previously pre-created
     * transaction from when you call the getPaymentLink() method.
     *
     * @param string $payReference The payReference value. This value is available
     *                             on the object returned when you call getPaymentLink() method.
     * @param string $session The session value. This is value available on the
     *                        object returned when you call getPaymentLink() method.
     * @return PaymentStatus
     * @throws Exception
     */
    public function getPaymentStatus(string $payReference, string $session): PaymentStatus
    {
        $ch = curl_init($this->getEndpoint("paymentStatus"));
        $optionsSet = curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "payReference: " . $payReference,
            ],
        ]);

        if (!$optionsSet) {
            throw new Exception("confirm payment status: error setting curl options: " . curl_error($ch));
        }

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("confirm payment status: making http request failed: " . curl_error($ch));
        }

        $payload = json_decode($response, true, 512, JSON_BIGINT_AS_STRING);
        if ($payload === null) {
            throw new Exception("confirm payment status: error json decoding response from server: " . json_last_error_msg());
        }

        return self::verifyPayment($payload, $session);
    }

    /**
     * Verifies that the data sent from PayFluid to the integrator is coming from
     * PayFluid and has not been tampered with.
     *
     * @param string|array $transactionDetails Either a valid JSON string or an array
     * @param string $session The session value from secure credentials
     * @return PaymentStatus
     * @throws Exception
     */
    public static function verifyPayment($transactionDetails, string $session): PaymentStatus
    {
        $payload = null;
        switch (true) {
            case is_string($transactionDetails):
                $payload = json_decode(urldecode($transactionDetails), true, 512, JSON_BIGINT_AS_STRING);
                if ($payload === null) {
                    throw new Exception("verify transaction: error json decoding transaction details: " . json_last_error_msg());
                }
                break;

            case is_array($transactionDetails):
                $payload = $transactionDetails;
                break;

            default:
                throw new InvalidArgumentException("verify transaction: argument 1 must be either a valid JSON string or an array, you passed: " . gettype($transactionDetails));
        }

        if (!array_key_exists("aapf_txn_signature", $payload)) {
            throw new Exception("verify transaction: no signature found");
        }
        if (empty($payload["aapf_txn_signature"])) {
            throw new Exception("verify transaction: signature exists but it is an empty string");
        }

        $signatureFromRequest = $payload["aapf_txn_signature"];
        unset($payload["aapf_txn_signature"]);

        $queryParams = join("", array_values($payload));

        $calculatedSignature = hash_hmac("sha256", $queryParams, md5($session));
        if (!hash_equals(strtoupper($calculatedSignature), strtoupper($signatureFromRequest))) {
            throw new Exception("verify transaction: signature is not valid");
        }

        $status = new PaymentStatus();
        $status->amount = $payload["aapf_txn_amt"];
        $status->redirectUrl = $payload["aapf_txn_clientRspRedirectURL"];
        $status->callbackUrl = $payload["aapf_txn_clientTxnWH"];
        $status->clientReference = $payload["aapf_txn_cref"];
        $status->currency = $payload["aapf_txn_currency"];
        $status->dateTime = $payload["aapf_txn_datetime"];
        $status->upstreamReference = $payload["aapf_txn_gw_ref"];
        $status->upstreamErrorCodeAndMsg = $payload["aapf_txn_gw_sc"];
        $status->maskedInstrument = $payload["aapf_txn_maskedInstr"];
        $status->payReference = $payload["aapf_txn_payLink"];
        $status->payScheme = $payload["aapf_txn_payScheme"];
        $status->payFluidReference = $payload["aapf_txn_ref"];
        $status->payFluidStatusCode = $payload["aapf_txn_sc"];
        $status->payFluidStatusMsg = $payload["aapf_txn_sc_msg"];
        $status->signature = $signatureFromRequest;
        return $status;
    }
}