<?php

declare(strict_types=1);

namespace Gitplus;

class PaymentLink
{
    public string $approvalCode;
    public string $resultMessage;
    public string $webUrl;
    public string $session;
    public string $resultCode;
    public string $payReference;
}