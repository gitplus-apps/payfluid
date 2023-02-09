<?php

namespace Gitplus\PayFluid;

class SecureCredentials
{
    public string $session;
    public string $rsaPublicKey;
    public string $sha256Salt;
    public string $kekExpiry;
    public string $macExpiry;
    public string $approvalCode;


    public function __construct(string $session, string $rsaPublicKey, string $sha256Salt,  int $kekExpiry, int $macExpiry, string $approvalCode)
    {
        $this->session = $session;
        $this->sha256Salt = $sha256Salt;
        $this->rsaPublicKey = $rsaPublicKey;
        $this->kekExpiry = $kekExpiry;
        $this->macExpiry = $macExpiry;
        $this->approvalCode = $approvalCode;
    }
}