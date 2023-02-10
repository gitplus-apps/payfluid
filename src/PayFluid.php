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

    private const baseUrl = "https://payfluid-api.herokuapp.com/payfluid/ext/api";

    /**
     * The various endpoints provided by the API
     *
     * @var array
     */
    protected array $endpoints = [
        "secureZone" => self::baseUrl . "/secureCredentials",
        "getPaymentLink" => self::baseUrl . "/getPayLink",
        "paymentStatus" => "https://www.payoutlet.com.gh/payfluid/ext/api/status?msg",
    ];

    public function __construct(string $apiId, string $apiKey, string $loginParameter)
    {
        $this->apiId = $apiId;
        $this->apiKey = $apiKey;
        $this->loginParameter = $loginParameter;
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

        $ch = curl_init($this->endpoints["secureZone"]);
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
     * @throws InvalidPaymentRequestException
     */
    private function validatePaymentObject(Payment $payment)
    {
        // Validate amount
        if (empty($payment->amount)) {
            throw new InvalidPaymentRequestException("validate payment: amount cannot be empty");
        }
        if (!filter_var($payment->amount, FILTER_VALIDATE_FLOAT)) {
            throw new InvalidPaymentRequestException(sprintf("validate payment: '%s' amount is not valid float or decimal", $payment->amount));
        }

        // Validate currency
        if (empty($payment->currency)) {
            throw new InvalidPaymentRequestException("validate payment: currency cannot be empty");
        }

        // Validate date time
        if (empty($payment->dateTime)) {
            throw new InvalidPaymentRequestException("validate payment: datetime cannot be empty: you must supply a date time string in the format 'Y-m-d\TH:i:s.v\Z'");
        }

        // Validate email
        if (empty($payment->email)) {
            throw new InvalidPaymentRequestException("validate payment: email cannot be empty");
        }
        if (!filter_var($payment->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidPaymentRequestException(sprintf("validate payment: '%s' is not a valid email", $payment->email));
        }

        // Validate phone number
        if (empty($payment->phone)) {
            throw new InvalidPaymentRequestException("validate payment: phone cannot be empty");
        }
        if (!is_numeric($payment->phone)) {
            throw new InvalidPaymentRequestException(sprintf("validate payment: '%s' is not a valid phone number: only digits allowed", $payment->phone));
        }

        // Validate reference
        if (empty($payment->reference)) {
            throw new InvalidPaymentRequestException("validate payment: reference cannot be empty");
        }
        $refLen = strlen($payment->reference);
        if ($refLen > 10) {
            throw new InvalidPaymentRequestException(sprintf("validate payment: reference cannot be more than 10 characters: your reference '%s' is %d characters long", $payment->reference, $refLen));
        }

        // Validate redirect and callback urls
        if (empty($payment->redirectUrl)) {
            throw new InvalidPaymentRequestException("validate payment: redirect url cannot be empty");
        }
        if (!empty($payment->callbackUrl) && ($payment->redirectUrl === $payment->callbackUrl)) {
            throw new InvalidPaymentRequestException("validate payment: the 'redirectUrl' and 'callbackUrl' cannot be the same");
        }
    }

    /**
     * Makes a request to PayFluid to initiate a payment.
     *
     * @param SecureCredentials $credentials
     * @param Payment $payment
     * @return PaymentLink A PaymentLink object with details about the payment link
     * @throws Exception
     */
    public function getPaymentLink(SecureCredentials $credentials, Payment $payment): PaymentLink
    {
        if (empty($credentials->session)) {
            throw new Exception("get payment link: the session value in credentials cannot be empty");
        }

        try {
            $this->validatePaymentObject($payment);
        } catch (Throwable $e) {
            throw new InvalidPaymentRequestException("get payment link: invalid payment object: " . $e->getMessage());
        }

        $requestBody = [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'datetime' => $payment->dateTime,
            'email' => $payment->email,
            'lang' => $payment->lang,
            'mobile' => $payment->phone,
            'name' => $payment->name,
            'reference' => $payment->reference,
            'responseRedirectURL' => $payment->redirectUrl,
            'session' => $credentials->session,
        ];

        if (!empty($payment->description)) {
            $requestBody["descr"] = $payment->description;
        }
        if (!empty($payment->otherInfo)) {
            $requestBody["otherInfo"] = $payment->otherInfo;
        }
        if (!empty($payment->callbackUrl)) {
            $requestBody["trxStatusCallbackURL"] = $payment->callbackUrl;
        }
        if (!empty($payment->customTxn)) {
            $requestBody["customTxn"] = $payment->customTxn;
        }

        ksort($requestBody);
        $signature = $this->createSignature($credentials, $requestBody);

        $requestBody = json_encode($requestBody, JSON_PRESERVE_ZERO_FRACTION);
        if ($requestBody === false) {
            throw new Exception("get payment link: error encoding request body to json: " . json_last_error_msg());
        }

        $ch = curl_init($this->endpoints["getPaymentLink"]);
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

        $response = json_decode($response);
        if ($response === null) {
            throw new Exception("get payment link: could not decode json response from server: " . json_last_error_msg());
        }

        if ($response->result_code !== "00") {
            throw new Exception("get payment link: " . $response->result_message);
        }

        $paymentLink = new PaymentLink();
        $paymentLink->approvalCode = $response->approvalCode;
        $paymentLink->resultMessage = $response->result_message;
        $paymentLink->session = $response->session;
        $paymentLink->webUrl = $response->webURL;
        $paymentLink->resultCode = $response->result_code;

        $payReference = explode("/", $response->webURL);
        $paymentLink->payReference = $payReference[count($payReference) - 1];

        return $paymentLink;
    }


    /**
     * @param string $payReference
     * @param string $session
     * @return PaymentStatus
     * @throws Exception
     */
    public function confirmPaymentStatus(string $payReference, string $session): PaymentStatus
    {
        $ch = curl_init();
        $optionsSet = curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $this->endpoints["paymentStatus"],
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
        $status->maskedPhoneNumber = $payload["aapf_txn_maskedInstr"];
        $status->payReference = $payload["aapf_txn_payLink"];
        $status->payScheme = $payload["aapf_txn_payScheme"];
        $status->payFluidReference = $payload["aapf_txn_ref"];
        $status->payFluidErrorCode = $payload["aapf_txn_sc"];
        $status->payFluidErrorMsg = $payload["aapf_txn_sc_msg"];
        $status->signature = $signatureFromRequest;
        return $status;
    }
}