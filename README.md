## A simple package that wraps [PayFluid's payment api](https://documenter.getpostman.com/view/1587357/SWDzdLcg#3b4e3a30-4714-4d21-a53a-1ca938618ede)

### INSTALLATION
You will need composer to install this package. You can [get it here](https://getcomposer.org/)
```bash
    composer require gitplus/payfluid
```


### USAGE

```php
<?php

require("vendor/autoload.php");

use Gitplus\PayFluid\PayFluid;
use Gitplus\PayFluid\Payment;

// Create a new payfluid client instance.
$payfluid = new PayFluid($apiId, $apiKey, $loginParameter);

// Get new secure credentials for subsequent requests to the API.
$credentials = $payfluid->getSecureCredentials($phoneNumber);

// Instantiate a new payment object.
$payment = new Payment();
$payment->amount = 1.0;
$payment->description = "Enter description for the payment";
$payment->email = $email;
$payment->phone = $phone;
$payment->name = $name;
$payment->otherInfo = "Package Number";
$payment->reference = bin2hex(random_bytes(5));
$payment->responseRedirectUrl = "https://4833-154-160-2-163.eu.ngrok.io";
$payment->trxStatusCallbackURL = "https://4833-154-160-2-163.eu.ngrok.io";
$payment->customTxn = "";

// Use payment object and secure credentials to get a payment link.
$paymentLink = $payfluid->getPaymentLink($credentials, $payment);

// You can then retrieve the payment url
$paymentLink->webUrl;
```
