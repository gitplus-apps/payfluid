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
    protected string $clientId;

    /**
     * The API key obtained from PayFluid
     *
     * @var string
     */
    protected string $encryptionKey;

    /**
     * The login parameter obtained from PayFluid
     * @var string
     */
    protected string $apiKey;

    private const TEST_BASE_URL = "https://payfluid-api.herokuapp.com/payfluid/ext/api";
    private const LIVE_BASE_URL = "https://www.payoutlet.com.gh/payfluid/ext/api";


    /**
     * The various endpoints provided by the API
     *
     * @var array
     */
    protected array $endpoints = [
        "test" => [
            "secureZone" => self::TEST_BASE_URL . "/secureCredentials",
            "getPaymentLink" => self::TEST_BASE_URL . "/getPayLink",
            "paymentStatus" => self::TEST_BASE_URL . "/status?msg",
        ],
        "live" => [
            "secureZone" => self::LIVE_BASE_URL . "/secureCredentials",
            "getPaymentLink" => self::LIVE_BASE_URL . "/getPayLink",
            "paymentStatus" => self::LIVE_BASE_URL . "/status?msg",
        ]
    ];

    protected bool $liveMode;

    /**
     * Instantiates a new PayFluid client.
     *
     * @param string $clientId The client id supplied from PayFluid
     * @param string $encryptionKey The RSA encryption key supplied from PayFluid
     * @param string $apiKey The API key supplied from PayFluid
     * @param bool $liveMode Indicates whether you are in live mode or test mode; true for live mode, false for test mode
     */
    public function __construct(string $clientId, string $encryptionKey, string $apiKey, bool $liveMode)
    {
        $this->clientId = $clientId;
        $this->encryptionKey = $encryptionKey;
        $this->apiKey = $apiKey;
        $this->liveMode = $liveMode;
    }

    /**
     * Returns the appropriate endpoint for either live or test mode.
     *
     * @param string $endpoint
     * @return string
     */
    private function getEndpoint(string $endpoint): string
    {
        $mode = $this->liveMode ? "live" : "test";
        return $this->endpoints[$mode][$endpoint];
    }


    /**
     * Generates an id for use as the API key in the header when getting secure
     * credentials.
     *
     * @param DateTime $now
     * @return string
     * @throws Exception
     */
    private function generateApiKeyHeader(DateTime $now): string
    {
        $rsa = new RSA();
        $keyLoaded = $rsa->loadKey($this->encryptionKey);
        if (!$keyLoaded) {
            throw new Exception("generate api key header: loading api key failed, please make sure your api key is correct");
        }

        $rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
        $data = sprintf("%s.%s", $this->apiKey, $now->format('YmdHisv'));
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
            throw new Exception("get secure credentials: encoding request body to json failed: " . json_last_error_msg());
        }

        $rsaPublicKey = $sha256Salt = "";

        try {
            $apiKeyHeader = $this->generateApiKeyHeader($now);
        } catch (Throwable $e) {
            throw new Exception("get secure credentials: " . $e->getMessage());
        }

        $ch = curl_init($this->getEndpoint("secureZone"));
        $optionsOk = curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "id: " . base64_encode($this->clientId),
                "apiKey: $apiKeyHeader",
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
        if (!$optionsOk) {
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
     * Checks to ensure that the payment object is valid
     *
     * @param Payment $payment
     * @throws Exception
     */
    private function validatePaymentObject(Payment $payment)
    {
        // Validate amount
        if (empty($payment->getAmount())) {
            throw new Exception("validate payment: amount cannot be empty or zero");
        }

        // Validate name
        if (empty($payment->getName())) {
            throw new Exception("validate payment: name cannot be empty");
        }

        // Validate currency
        if (empty($payment->currency())) {
            throw new Exception("validate payment: currency cannot be empty");
        }

        // Validate date time
        if (empty($payment->getDateTime())) {
            throw new Exception("validate payment: datetime cannot be empty: you must supply a date time string in the format 'Y-m-d\TH:i:s.v\Z'");
        }

        // Validate email
        if (empty($payment->getEmail())) {
            throw new Exception("validate payment: email cannot be empty");
        }

        // Validate phone number
        if (empty($payment->getPhone())) {
            throw new Exception("validate payment: phone cannot be empty");
        }

        // Validate reference
        if (empty($payment->getReference())) {
            throw new Exception("validate payment: reference cannot be empty");
        }

        // Validate redirect and callback urls
        if (empty($payment->getRedirectUrl())) {
            throw new Exception("validate payment: redirect url cannot be empty");
        }
    }


    /**
     * Creates a signature for use in requests to the server
     *
     * @param SecureCredentials $credentials
     * @param array $requestBody
     * @return string A base64 encoded string of the signature
     * @throws Exception
     */
    private function signRequest(SecureCredentials $credentials, array $requestBody): string
    {
        $requestBodyAsString = join("", array_values($requestBody));

        // $requestBodyAsString = "";
        // array_walk_recursive($requestBody, function ($value, $key) use (&$requestBodyAsString) {
        //     $requestBodyAsString .= $value;
        // });

        $rsa = new RSA();
        $rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
        $keyLoaded = $rsa->loadKey($credentials->rsaPublicKey);
        if (!$keyLoaded) {
            throw new Exception("sign request: loading rsa public key failed");
        }

        $hash = hash_hmac("sha256", $requestBodyAsString, $credentials->sha256Salt);
        return base64_encode($rsa->encrypt($hash));
    }


    /**
     * Makes a request to PayFluid to initiate a payment.
     *
     * @param SecureCredentials $credentials
     * @param Payment $payment
     * @return PaymentLink A PaymentLink object with the url to the payment page
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
            throw new Exception("get payment link: " . $e->getMessage());
        }

        $requestBody = [
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'datetime' => $payment->getDateTime(),
            'email' => $payment->getEmail(),
            'lang' => $payment->getLanguage(),
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
            $requestBody["customTxn"] = $payment->getCustomization()->toArray();
        }

        ksort($requestBody);
        $signature = $this->signRequest($credentials, $requestBody);

        $requestBody = json_encode($requestBody, JSON_PRESERVE_ZERO_FRACTION);
        if ($requestBody === false) {
            throw new Exception("get payment link: error encoding request body to json: " . json_last_error_msg());
        }

        $ch = curl_init($this->getEndpoint("getPaymentLink"));
        $optionsOk = curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "signature: " . $signature,
            ],
            CURLOPT_POSTFIELDS => $requestBody
        ]);
        if (!$optionsOk) {
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
                    "get payment link: could not decode json, upstream server response '%s': json: %s",
                    $response,
                    json_last_error_msg()
                )
            );
        }

        if ($result->result_code !== "00") {
            throw new Exception(sprintf("get payment link: request failed with error code: %d and error message: '%s'", $result->result_code, $result->result_message));
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
     * Verifies that the data sent from PayFluid to the integrator is indeed
     * coming from PayFluid and has not been tampered with.
     *
     * @param string|array $paymentDetails Either a valid JSON string or an array
     * @param string $session The session value from secure credentials
     * @return PaymentStatus
     * @throws Exception
     */
    public static function verifyPayment($paymentDetails, string $session): PaymentStatus
    {
        $payload = null;
        switch (true) {
            case is_string($paymentDetails):
                $payload = json_decode(urldecode($paymentDetails), true, 512, JSON_BIGINT_AS_STRING);
                if ($payload === null) {
                    throw new Exception("verify transaction: error json decoding transaction details: " . json_last_error_msg());
                }
                break;

            case is_array($paymentDetails):
                $payload = $paymentDetails;
                break;

            default:
                throw new InvalidArgumentException("verify transaction: argument 1 must be either a valid JSON string or an array, you passed: " . gettype($paymentDetails));
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
        $status->upStreamReference = $payload["aapf_txn_gw_ref"];
        $status->upStreamDebitStatus = $payload["aapf_txn_gw_sc"];
        $status->maskedInstrument = $payload["aapf_txn_maskedInstr"];
        $status->payReference = $payload["aapf_txn_payLink"];
        $status->payScheme = $payload["aapf_txn_payScheme"];
        $status->payFluidReference = $payload["aapf_txn_ref"];
        $status->statusCode = $payload["aapf_txn_sc"];
        $status->statusString = $payload["aapf_txn_sc_msg"];
        $status->signature = $signatureFromRequest;
        return $status;
    }


    /**
     * Retrieves and lets you confirm the status of a previously pre-created
     * transaction from when you call the getPaymentLink() method.
     *
     * @param string $payReference The payReference value. This value is available
     *                             on the object returned when you call getPaymentLink()
     *                             method.
     *
     * @param string $session The session value. This is value available on the
     *                        object returned when you call getPaymentLink()
     *                        method.
     *
     * @return PaymentStatus
     * @throws Exception
     */
    public function getPaymentStatus(string $payReference, string $session): PaymentStatus
    {
        if ($payReference === "") {
            throw new InvalidArgumentException("confirm payment status: payReference cannot be empty");
        }
        if ($session === "") {
            throw new InvalidArgumentException("confirm payment status: session cannot be empty");
        }

        $ch = curl_init($this->getEndpoint("paymentStatus"));
        $optionsOk = curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "payReference: " . $payReference,
            ],
        ]);

        if (!$optionsOk) {
            throw new Exception("confirm payment status: error preparing request: " . curl_error($ch));
        }

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("confirm payment status: request failed: " . curl_error($ch));
        }

        $payload = json_decode($response, true, 512, JSON_BIGINT_AS_STRING);
        if ($payload === null) {
            throw new Exception(sprintf("confirm payment status: could not decode server response: `%s`, json error: `%s`", $response, json_last_error_msg()));
        }

        return self::verifyPayment($payload, $session);
    }
}