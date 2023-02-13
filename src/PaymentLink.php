<?php

declare(strict_types=1);

namespace Gitplus\PayFluid;

/**
 * PaymentLink represents a newly created payment page.
 *
 * The 'webUrl' property has a link to the actual payment page.
 * Also, the 'payReference' and 'session' properties are important
 * for later verification of a payment.
 */
class PaymentLink
{
    public string $approvalCode;
    public string $resultMessage;
    public string $webUrl;
    public string $session;
    public string $resultCode;
    public string $payReference;
}