<?php

declare(strict_types=1);

namespace Gitplus\PayFluid;

/**
 * PaymentStatus is the status of a payment.
 *
 * The most important properties to check are 'statusCode' and 'statusString'.
 * If 'statusCode' === '0' then the payment is successful. Any other value
 * means there was an issue with the payment and the 'statusString' will
 * have a string explaining the meaning of the status code.
 */
class PaymentStatus
{
    /**
     * The payment amount
     *
     * @var string
     */
    public string $amount;

    /**
     * The redirect url for the payment
     *
     * @var string
     */
    public string $redirectUrl;

    /**
     * The callback url for the payment
     *
     * @var string
     */
    public string $callbackUrl;

    /**
     * The reference you used for this payment
     *
     * @var string
     */
    public string $clientReference;

    /**
     * The currency for the payment
     *
     * @var string
     */
    public string $currency;

    /**
     * Date and time the customer made the payment as recorded by PayFluid servers
     *
     * @var string
     */
    public string $dateTime;

    /**
     * The reference received by PayFluid from the actual Payment gateway that
     * was used for the debit.
     *
     * @var string
     */
    public string $upStreamReference;

    /**
     * The debit status code as received from the Payment gateway by PayFluid
     *
     * @var string
     */
    public string $upStreamDebitStatus;

    /**
     * The masked number of the instrument of payment used. E.g. phone number or card number.
     * @var string
     */
    public string $maskedInstrument;

    /**
     * This identifies the payment link used to make payment.
     * This is what you will pass to make a transaction status check.
     * In the rare case where you do not receive a callback.
     *
     * @var string
     */
    public string $payReference;

    /**
     * The payment scheme used. E.g. MTNMM, MASTERCARD etc.
     * @var string
     */
    public string $payScheme;

    /**
     * The transaction reference generated by PayFluid for this payment
     *
     * @var string
     */
    public string $payFluidReference;

    /**
     * The transaction status code.
     *
     * If this is '0', the transaction is successful.
     * Any other value and you will have to look at the "statusString" for interpretation.
     *
     * @var string
     */
    public string $statusCode;

    /**
     * Explains the code in "statusCode".
     * It is an interpretation of what "statusCode" means.
     *
     * @var string
     */
    public string $statusString;

    /**
     * The signature for the payload.
     * This must be verified to indicate that notification truly came from PayFluid.
     * @var string
     */
    public string $signature;

    /**
     * Get an array representation of this payment status
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            "amount" => $this->amount,
            "redirectUrl" => $this->redirectUrl,
            "callbackUrl" => $this->callbackUrl,
            "clientReference" => $this->clientReference,
            "currency" => $this->currency,
            "dateTime" => $this->dateTime,
            "upStreamReference" => $this->upStreamReference,
            "upStreamDebitStatus" => $this->upStreamDebitStatus,
            "maskedInstrument" => $this->maskedInstrument,
            "payReference" => $this->payReference,
            "payScheme" => $this->payScheme,
            "payFluidReference" => $this->payFluidReference,
            "statusCode" => $this->statusCode,
            "statusString" => $this->statusString,
            "signature" => $this->signature,
        ];
    }

    /**
     * Get a JSON string representation of this payment status.
     * Useful if you will want to store this payment status for later.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}

