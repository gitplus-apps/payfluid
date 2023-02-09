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
        $now = new DateTime();
        $requestBody = json_encode([
            "cmd" => "getSecureParams",
            "datetime" => $now->format('YmdHisv'),
            "mobile" => $phoneNumber,
        ]);

        if ($requestBody === false) {
            throw new Exception("creating secure zone failed: encoding the request body to json failed: " . json_last_error_msg());
        }

        $responseHeaders = [];

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
            CURLOPT_HEADERFUNCTION => function ($curl, $currentHeader) use (&$responseHeaders) {
                $headerLength = strlen($currentHeader);
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
            throw new Exception("failed to create secure credentials: " . curl_error($ch));
        }

        $response = json_decode($response, false);
        if ($response === false) {
            throw new Exception("could not create secure credentials: decoding json response failed: " . json_last_error_msg());
        }

        if ($response->resultCode !== "00") {
            throw new Exception("could not create secure credentials: " . $response->resultMessage);
        }

        $rsaPublicKeyAndsha256Salt = explode(".", $responseHeaders["kek"]);

        return new SecureCredentials(
            $response->session,
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
    private function validatePaymentRequest(Payment $payment)
    {
        // Validate amount
        if (empty($payment->amount)) {
            throw new InvalidPaymentRequestException("payment amount cannot be empty");
        }
        if (!is_float(floatval($payment->amount))) {
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

        if (!empty($payment->responseRedirectUrl) && !empty($payment->trxStatusCallbackURL)) {
            if ($payment->responseRedirectUrl === $payment->trxStatusCallbackURL) {
                throw new InvalidPaymentRequestException("the 'responseRedirectUrl' and 'trxStatusCallbackURL' cannot be the same");
            }
        }
    }

    /**
     * Makes a request to PayFluid to initiate a payment.
     *
     * @param SecureCredentials $credentials
     * @param Payment $payment
     * @return PaymentLink The redirect url
     * @throws Exception
     */
    public function getPaymentLink(SecureCredentials $credentials, Payment $payment): PaymentLink
    {
        if (empty($credentials->session)) {
            throw new Exception("the session value in credentials cannot be empty");
        }

        try {
            $this->validatePaymentRequest($payment);
        } catch (Throwable $e) {
            throw new InvalidPaymentRequestException("invalid payment object: ". $e->getMessage());
        }

        $requestBody = [
            'amount' => floatval($payment->amount),
            'currency' => $payment->currency,
            'datetime' => $payment->dateTime,
            'descr' => $payment->description,
            'email' => $payment->email,
            'lang' => $payment->lang,
            'mobile' => $payment->phone,
            'name' => $payment->name,
            'otherInfo' => $payment->otherInfo,
            'reference' => $payment->reference,
            'responseRedirectURL' => $payment->responseRedirectUrl,
            'session' => $credentials->session,
            'trxStatusCallbackURL' => $payment->webhookUrl,
            'customTxn' => $payment->customTxn,
        ];

        ksort($requestBody);
        $signature = $this->createSignature($credentials, $requestBody);

        $requestBody = json_encode($requestBody, JSON_PRESERVE_ZERO_FRACTION);
        if ($requestBody === false) {
            throw new Exception("could not get payment link: error trying to encode request body to json: " . json_last_error_msg());
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
            throw new Exception("get payment link request failed: " . curl_error($ch));
        }

        $response = json_decode($response);
        if ($response === false) {
            throw new Exception("get payment link failed: could not decode json response from server: " . json_last_error_msg());
        }

        if ($response->result_code !== "00") {
            throw new Exception("get payment link failed: " . $response->result_message);
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
            throw new Exception("could not get payment status: ". curl_error($ch));
        }

        return $response;
    }
}