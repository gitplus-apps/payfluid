<?php

namespace Gitplus\PayFluid;

class PaymentStatus {
    public string $amount;
    public string $redirectUrl;
    public string $callbackUrl;
    public string $clientReference;
    public string $currency;
    public string $dateTime;
    public string $upstreamReference;
    public string $upstreamErrorCodeAndMsg;
    public string $maskedPhoneNumber;

    public string $payReference;
    public string $payScheme;

    public string $payFluidReference;
    public string $payFluidErrorCode;
    public string $payFluidErrorMsg;
    public string $signature;

    public function __construct() {}
}