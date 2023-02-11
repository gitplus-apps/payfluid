<?php
declare(strict_types=1);

namespace Gitplus\PayFluid;

class PaymentStatus
{
    public string $amount;
    public string $redirectUrl;
    public string $callbackUrl;
    public string $clientReference;
    public string $currency;
    public string $dateTime;
    public string $upstreamReference;
    public string $upstreamErrorCodeAndMsg;
    public string $maskedInstrument;

    public string $payReference;
    public string $payScheme;

    public string $payFluidReference;
    public string $payFluidStatusCode;
    public string $payFluidStatusMsg;
    public string $signature;
}