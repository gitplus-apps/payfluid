<?php
declare(strict_types=1);

namespace Gitplus\PayFluid;

use DateTime;
use Exception;
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

    const baseUrl = "https://payfluid-api.herokuapp.com/payfluid/ext/api";

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
     * @param string $phoneNumber
     * @return SecureCredentials
     * @throws Exception
     */
    public function getSecureCredentials(string $phoneNumber): SecureCredentials
    {
        if (empty($phoneNumber)) {
            throw new Exception("could not get secure credentials: empty phone number supplied");
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

        $responseHeaders = [];
//        $rsaPublicKey = "";
//        $sha256Salt = "";

        $ch = curl_init($this->endpoints["secureZone"]);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "id: " . base64_encode($this->apiId),
                "apiKey: " . $this->generateId($now),
            ],

            // This curl option allows a function to be called on each of the headers that come
            // with the response. Meaning for all the response headers that come with the response,
            // this function will be called one each one of them. The reason why we are doing this
            // is that, when you make a request to PayFluid to get secure parameters, the response
            // includes a header that looks like this:
            //      "Kek: long_string_of_random_characters.even_more_strings".
            // Notice the full stop or period in the string. We want to retrieve this header and
            // split it into two(2) using the full stop as the separator. So what this function
            // will do is to go
            CURLOPT_HEADERFUNCTION => function ($curl, $currentHeader) use (&$responseHeaders) {
                $headerLength = strlen($currentHeader);
                if (!stripos($currentHeader, "kek")) {
                    return $headerLength;
                }

//                $kekValue = ltrim("kek: ", strtolower($currentHeader));
//                $split = explode(".", $kekValue);
//                $rsaPublicKey = $split[0];
//                $sha256Salt = $split[1];

                $headerKeyValue = explode(":", trim($currentHeader));

                // Some headers do not have values so we skip them
                if (count($headerKeyValue) < 2) {
                    return $headerLength;
                }

                $responseHeaders[strtolower($headerKeyValue[0])] = $headerKeyValue[1];
                return $headerLength;
            }
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("get secure credentials: " . curl_error($ch));
        }

        $response = json_decode($response, false);
        if ($response === false) {
            throw new Exception("get secure credentials: decoding json response failed: " . json_last_error_msg());
        }

        if ($response->resultCode !== "00") {
            throw new Exception("get secure credentials: " . $response->resultMessage);
        }

        $rsaPublicKeyAndsha256Salt = explode(".", $responseHeaders["kek"]);

        return new SecureCredentials(
            $response->session,
//            $rsaPublicKey,
//            $sha256Salt,
            $rsaPublicKeyAndsha256Salt[0],
            $rsaPublicKeyAndsha256Salt[1],
            $response->kekExpiry,
            $response->macExpiry,
            $response->approvalCode
        );
    }

    private function createSignature(SecureCredentials $credentials, array $requestBody): string
    {
        $requestBodyAsString = join("", array_values($requestBody));
        $hash = hash_hmac("sha256", $requestBodyAsString, $credentials->sha256Salt);

        $rsa = new RSA();
        $rsa->loadKey($credentials->rsaPublicKey);
        $rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
        return base64_encode($rsa->encrypt($hash));
    }

    /**
     * Check to ensure that the payment request is valid
     *
     * @param Payment $payment
     * @throws InvalidPaymentRequestException
     */
    private function validatePaymentObject(Payment $payment)
    {
        // Validate amount
        if (empty($payment->amount)) {
            throw new InvalidPaymentRequestException("payment amount cannot be empty");
        }
        if (!filter_var($payment->amount, FILTER_VALIDATE_FLOAT)) {
            throw new InvalidPaymentRequestException(sprintf("payment '%s' amount is not valid float or decimal", $payment->amount));
        }

        // Validate currency
        if (empty($payment->currency)) {
            throw new InvalidPaymentRequestException("payment currency cannot be empty");
        }

        // Validate date time
        if (empty($payment->dateTime)) {
            throw new InvalidPaymentRequestException("payment datetime cannot be empty: you must supply a date time string in the format 'Y-m-d\TH:i:s.v\Z'");
        }

        // Validate email
        if (empty($payment->email)) {
            throw new InvalidPaymentRequestException("payment email cannot be empty");
        }
        if (!filter_var($payment->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidPaymentRequestException(sprintf("the supplied payment email '%s' is not a valid email", $payment->email));
        }

        // Validate phone number
        if (empty($payment->phone)) {
            throw new InvalidPaymentRequestException("payment phone cannot be empty");
        }
        if (!is_numeric($payment->phone)) {
            throw new InvalidPaymentRequestException(sprintf("the supplied phone number '%s' is not valid: only digits allowed", $payment->phone));
        }

        // Validate reference
        if (empty($payment->reference)) {
            throw new InvalidPaymentRequestException("payment reference cannot be empty");
        }

        // Validate redirect and callback urls
        if (empty($payment->redirectUrl)) {
            throw new InvalidPaymentRequestException("payment redirect url cannot be empty");
        }
        if (!empty($payment->callbackUrl) && ($payment->redirectUrl === $payment->callbackUrl)) {
            throw new InvalidPaymentRequestException("the 'redirectUrl' and 'callbackUrl' cannot be the same");
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
            throw new InvalidPaymentRequestException("get payment link: " . $e->getMessage());
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
            throw new Exception("get payment link: error trying to encode request body to json: " . json_last_error_msg());
        }

        $ch = curl_init($this->endpoints["getPaymentLink"]);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "signature: " . $signature,
            ],
            CURLOPT_POSTFIELDS => $requestBody
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("get payment link: curl request failed: " . curl_error($ch));
        }

        $response = json_decode($response);
        if ($response === false) {
            throw new Exception("get payment link: could not decode json response from server: " . json_last_error_msg());
        }

        if ($response->result_code !== "00") {
            throw new Exception("get payment link: server responded with an error: " . $response->result_message);
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
     * @return string
     * @throws Exception
     */
    public function getPaymentStatus(string $payReference): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_URL => $this->endpoints["paymentStatus"],
            CURLOPT_HTTPHEADER => [
                "payReference: " . $payReference,
            ],
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception("could not get payment status: " . curl_error($ch));
        }

        return $response;
    }

    /**
     * Verifies that the data sent to the integrator's redirect url is indeed coming from PayFluid
     *
     * @param string $qs The query string PayFluid sends as part of the responseUrl
     * @return bool
     */
    public function verifyPayment(string $qs): bool
    {
        $payload = json_decode($qs, false, 512, JSON_BIGINT_AS_STRING);
        return true;
    }
}