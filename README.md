## A simple package that wraps [PayFluid's payment api](https://documenter.getpostman.com/view/1587357/SWDzdLcg#3b4e3a30-4714-4d21-a53a-1ca938618ede)

### INSTALLATION
You will need composer to install this package. You can [get it here](https://getcomposer.org/)
```bash
    composer require gitplus/payfluid
```


### USAGE
> Please note that the IP address of your host device (where you are making requests from) must be whitelisted by PayFluid
> for any of these to work.   

> #### Do know that error handling has been intentionally left out of these examples for brevity
### Get URL to payment portal
```php
<?php

require("vendor/autoload.php");

use Gitplus\Payfluid\PayFluid;
use Gitplus\Payfluid\Payment;


try {
    // Create a new PayFluid client instance.
    $payfluid = new PayFluid($apiId, $apiKey, $loginParameter);
    
    // Get secure credentials for subsequent requests to the API.
    // Credentials here has your session value. It is a good idea to keep
    // it for later. You will need it to verify payments later.
    $credentials = $payfluid->getSecureCredentials($phoneNumber);
    
    // Instantiate a new payment object.
    $payment = new Payment();
    $payment->amount = 1.0;
    $payment->description = "Enter description for the payment";
    $payment->email = $email;
    $payment->phone = $phone;
    $payment->name = $name;
    $payment->otherInfo = "Any extra information";
    $payment->reference = bin2hex(random_bytes(5)); // Must be unique and not more than 10 characters.
    $payment->redirectUrl = "https://your/redirect/url";
    $payment->callbackUrl = "https://your/callback/webhook/url";
    
    // Use payment object and secure credentials to get a payment link.
    // $paymentLink also has your session. You can use the session value
    // to verify payments later.
    $paymentLink = $payfluid->getPaymentLink($credentials, $payment);
    
    // You can then retrieve the payment url.
    // It also has several fields like the session, which is used to verify
    // transactions after payment has be made.
    $paymentLink->webUrl;
} catch (\Throwable $e) {
    // Handle error
    echo "Generating payment url"
}

```

### Verify transaction on your redirect URL
```php
<?php

require("vendor/autoload.php");

use Gitplus\Payfluid\PayFluid;

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    try {
        // The request to your redirect URL will come with a 'qs' query parameter.
        // This is where you use the session value from when you created your secure
        // credentials. Throws an exception if anything goes wrong. The $transactionDetails
        // object has details on whether the transaction was successful or not.
        $transactionDetails = PayFluid::verifyPayment($_GET["qs"], $session);
    } catch (\Throwable $e) {
        echo "Verifying payment failed: " . $e->getMessage();
    }   
}
```

### Verify transaction on your callback/webhook URL
```php
require("vendor/autoload.php");

use Gitplus\Payfluid\PayFluid;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Read JSON body from request
        $payload = json_decode(file_get_contents("php://input"));
    
        // NOTE: The session is from either your secureCredentials or paymentLink object
        // created in earlier examples. The $transactionDetails object has details on 
        // the success or otherwise of the transaction.
        $transactionDetails = PayFluid::verifyPayment($payload, $session);
    } catch (\Throwable $e) {
        echo "Verifying transaction failed: " . $e->getMessage();
    }
}
```

### CONTRIBUTIONS
Contributions and improvements are welcomed wholeheartedly.