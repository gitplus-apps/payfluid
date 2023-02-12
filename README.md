# A package that wraps eTranzact's [PayFluid payment API](https://documenter.getpostman.com/view/1587357/SWDzdLcg#3b4e3a30-4714-4d21-a53a-1ca938618ede)

## 📌 Overview
This package helps you integrate PayFluid's payment system into your application in an easy and simple way.  
The package covers all the endpoints exposed by PayFluid.
* Generate secure credentials.
* Generate a payment link to collect payments.
* Verify payment details sent to your redirect url and callback url.
* Get the status of a previously made payment.
* Customize the payment page and also customize how the payment link behaves.
<br>

## Contents
- [📌 Overview](#-overview)
- [⏳ Installation](#-installation)

- [👼 Basic Usage](#-basic-usage)
   - [Quick Start - Generate Payment Link](#1-generate-payment-link)
   - [Verify Payment - (Redirect URL)](#2-verify-transaction-on-your-redirect-url)
   - [Verify Payment - (Callback/Webhook URL)](#3-verify-transaction-on-your-callbackwebhook-url)
   - [Get Payment Status](#4-check-or-confirm-the-status-of-a-previous-payment)

- [💪 Advanced Usage](#-advanced-usage)
  - [Extra Required Fields](#1-extra-required-fields)
  - [Customize Payment Page and Link](#2-customize-payment-page-and-link-behaviour)

- [✌️ Tips and Tricks](#-tips)
  - [Pass and retrieve session values from urls](#1-pass-and-retrieve-session-value-from-redirect-or-callback-url)

- [⚠️ Issues](#-issues)
-  [👊 Contributions](#-contributions)

<br>

## ⏳ Installation

You will need composer to install this package. You can [get it here](https://getcomposer.org/)
```bash
composer require gitplus/payfluid
```
<br>

## 👼 Basic Usage

> #### NB: Please note that the IP address of your host device (where you are making requests from) must be whitelisted by PayFluid for any of these to work.  

> #### Kindly note that error handling has been intentionally left out of these examples for brevity.
### 1. Generate payment link.
Here a quick start to get you going quickly.
```php
<?php

require("vendor/autoload.php");

use Gitplus\PayFluid\PayFluid;
use Gitplus\PayFluid\Payment;


try {
    // Create a new PayFluid client instance. The fourth(4th) parameter is
    // a boolean that indicates whether you are in live or test mode.
    // 'TRUE' for live mode, 'FALSE' for test mode.
    $payfluid = new PayFluid($apiId, $apiKey, $loginParameter, $testOrLiveMode);
    
    // Get secure credentials to authenticate with the server.
    // The returned $credentials object here has your 'session' value.
    // It is a good idea to store it for later use. You will need it to
    // verify payments.
    $credentials = $payfluid->getSecureCredentials($phoneNumber);
    
    // Create a new payment object and set the required and any optional fields.
    // You can chain the methods.
    $payment = new Payment();
    $payment->amount(1.0)
        ->email($email)
        ->phone($phoneNumber)
        ->name($name)
        ->reference(bin2hex(random_bytes(5)))                   // A unique alphanumeric string. Maximum of 10 characters.
        ->description("Enter description for the payment")      // A description for the transaction/payment. Maximum of 40 characters.
        ->redirectUrl("https://your/redirect/url")              // Your user will be redirected here after paying.
        ->callbackUrl("https://your/callback_or_webhook/url")   // This is your webhook. Details of the payment will be sent here
        ->otherInfo("Any extra information");                   // Any extra information.
    
    // You can now get a payment link object.
    // Use both the payment object and secure credentials to get a payment link.
    $paymentLink = $payfluid->getPaymentLink($credentials, $payment);
    
    // You can then retrieve the web url and redirect your user to that location.
    // 
    // NOTE:
    // The $paymentLink object will also have your 'session' and 'payReference' values.
    // It is a good idea to store these values for later. You will need them to
    // verify payments or to retrieve the status of a particular payment later.
    $paymentLink->webUrl;
} catch (\Throwable $e) {
    // Handle error
    echo "Generating payment url failed: " . $e->getMessage();
}
```

### 2. Verify transaction on your redirect URL.
Here is how you can verify a payment when the details get sent to your
redirect url.
```php
<?php

require("vendor/autoload.php");

use Gitplus\PayFluid\PayFluid;

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    try {
        // The request from PayFluid to your redirect URL will come with a 'qs' query parameter.
        // Don't worry about calling urldecode() on the 'qs' query parameter. It is handled internally.
        $qs = $_GET["qs"];
       
        // Use qs and your session value to verify the payment.
        // You can get your session value from two(2) places:
        //      1. From the $credentials object you got when you called the getSecureCredentials() method.
        //      2. From the $paymentLink object you got when you called the getPaymentLink() method
        //      It is a good idea to have stored these values somewhere earlier.
        //
        // The $paymentStatus object returned will have details on whether the transaction was successful or not.
        $paymentStatus = PayFluid::verifyPayment($qs, $session);
        
        // If statusCode = '0' then the payment was successful. Any other value
        // means the transaction failed. The statusString field will explain what
        // the code means.
        if ($paymentStatus->statusCode === "0") {
            // You can convert the payment status to a JSON string and perhaps store it for future reference
            $statusAsJson = $paymentStatus->toJson();
            
            // You can also retrieve it as an array if you want
            $statusAsArray = $paymentStatus->toArray();
            
            echo "Payment successful";
        } else {
            echo "Payment failed: " . $paymentStatus->statusString;
        }
    } catch (\Throwable $e) {
        echo "Verifying payment failed: " . $e->getMessage();
    }   
}
```

### 3. Verify transaction on your callback/webhook URL.
Here is how you can verify a payment when the details get sent to your
callback/webhook url.
```php
<?php

require("vendor/autoload.php");

use Gitplus\PayFluid\PayFluid;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Read JSON body from request
        $payload = json_decode(file_get_contents("php://input"));
    
        // NOTE:
        // The $session is from either your $secureCredentials or $paymentLink objects created earlier.
        // The $transactionDetails object returned has details on the success or otherwise of the payment.
        $statusCode = PayFluid::verifyPayment($payload, $session);
        
        // If statusCode = '0' then the payment was successful. Any other value
        // means the transaction failed. The statusString field will explain what
        // the code means.
        if ($paymentStatus->statusCode === "0") {
            // You can convert the payment status to a JSON string and perhaps store it for future reference
            $statusAsJson = $paymentStatus->toJson();
            
            // You can also retrieve it as an array if you want
            $statusAsArray = $paymentStatus->toArray();
            
            echo "Payment successful";
        } else {
            echo "Payment failed: " . $paymentStatus->statusString;
        }
    } catch (\Throwable $e) {
        echo "Verifying payment failed: " . $e->getMessage();
    }
}
```

### 4. Check or confirm the status of a previous payment.
Here is how you can contact PayFluid servers to find out about the status of a
particular payment.
````php
<?php

require("vendor/autoload.php");

use Gitplus\PayFluid\PayFluid;

try {
    // Create a new PayFluid client instance.
    $payfluid = new PayFluid($apiId, $apiKey, $loginParameter, $testOrLiveMode);
    
    // getPaymentStatus() will return a PaymentStatus object with details information on the status of the payment.
    // The $payReference is from the $paymentLink object you created earlier.
    // The $session is from either the $paymentLink or $credentials objects you created earlier.
    $paymentStatus = $payfluid->getPaymentStatus($payReference, $session);
    
    // If statusCode = '0' then the payment was successful. Any other value
    // means the transaction failed. The statusString field will explain what
    // the code means.
    if ($paymentStatus->statusCode === "0") {
        // You can convert the payment status to a JSON string and perhaps store it for future reference
        $statusAsJson = $paymentStatus->toJson();
        
        // You can also retrieve it as an array if you want
        $statusAsArray = $paymentStatus->toArray();
 
        echo "Payment successful";
    } else {
        echo "Payment failed: " . $paymentStatus->statusString;
    }
} catch (\Throwable $e) {
    // Handle error
    echo "Getting payment status failed: " . $e->getMessage();
}
````
<br>

## 💪 Advanced Usage

### 1. Extra required fields
Here are details about some extra required fields. These fields are required,
but they have default fields, so you can ignore them.
```php
<?php

require("vendor/autoload.php");

use Gitplus\PayFluid\PayFluid;
use Gitplus\PayFluid\Payment;


try {
    // Create a new PayFluid client instance.
    $payfluid = new PayFluid($apiId, $apiKey, $loginParameter, $testOrLiveMode);
    
    // Get secure credentials.
    // Note that the $credentials object here has your 'session' value.
    // It is a good idea to store it for later use. You will need it to
    // verify payments later.
    $credentials = $payfluid->getSecureCredentials($phoneNumber);
    
    // Create a new payment object.
    $payment = new Payment();
    
    // These fields are absolutely required.
    $payment->amount(1.0)
        ->email($email)
        ->phone($phoneNumber)
        ->name($name)
        ->reference(bin2hex(random_bytes(5)))
        ->redirectUrl("https://your/redirect/url");
  
    // These fields are also required, but they have default values, so you can ignore them.
    // The default values will be used if you don't set them.
    // If you do NEED to change them, you can do so.
    // Here they with their default values:
    $payment->currency("GHS")->lang("en");
    
    // Optional fields
    $payment->description("Enter description for the payment")
        ->callbackUrl("https://your/callback_or_webhook/url")
        ->otherInfo("Any extra information");
    
    // Get payment link
    $paymentLink = $payfluid->getPaymentLink($credentials, $payment);
    
    // You can then retrieve the payment url and redirect your user to that location.
    // This $paymentLink object will have your 'session' and 'payReference' values.
    // It is a good idea to save these values for later. You will need them to
    // verify payments or retrieve the status of a particular payment.
    $paymentLink->webUrl;
} catch (\Throwable $e) {
    // Handle error
    echo "Generating payment url failed: " . $e->getMessage();
}
```
### 2. Customize payment page and link behaviour.
PayFluid gives you some flexibility. You can customize how the web url you get behaves
and also customize how the payment page that will be presented to your user will look like.
Below is an example of how you can achieve that.

```php
<?php

require("vendor/autoload.php");

use Gitplus\PayFluid\PayFluid;
use Gitplus\PayFluid\Payment;
use Gitplus\PayFluid\Customization;
use Gitplus\PayFluid\CustomerInput;

try {    
    // Create a new customization object.
    $customization = new Customization();
    $customization->editAmount(true)            // The payment amount can be edited by the user
        ->minimumAmount(1.0)                    // Enforce the minimum amount to pay
        ->maximumAmount(30.0)                   // Enforce the maximum amount to pay
        ->borderTheme("#a3ffee")                // Set a color for the page
        ->receiptMessage("Thank you for your purchase")    // Override the message sent in receipt
        ->receiptFeedbackPhone("233XXXXXXX")               // Override the phone number that gets the receipt
        ->receiptFeedbackEmail("user@domain.com")          // Override the email that receives the receipt
        ->daysUntilLinkExpires(3)                          // Determine how long the payment link should be valid for
        ->canPayMultipleTimes(true)                        // Payment links are one time. This will make the link reusable
        ->displayPicture("https://link/to/publicly/accessible/image");  // Set your own image to be displayed on the payment page.
        
    // You can take your customization further.
    // PayFluid gives you the flexibility to even ask for more information on the
    // payment page. You do this by creating input fields. The fields will be
    // rendered on the payment page for the customer to provide answers to.
    // To achieve this you need to create CustomerInput objects and add
    // them to your customization object.
    
    // Create your first input. This will be a text input.
    $textInput = new CustomerInput();
    $textInput->label("Church Membership ID")    // The label for the input
        ->placeholder("Membership ID #")         // The placeholder for the input
        ->type(CustomerInput::TYPE_TEXT)    // The type of input
        ->required(true);                        // Indicate whether the input is required or not.
        
    // Create another input but this time it will be a select dropdown.
    $selectInput = new CustomerInput();
    $selectInput->label("Offering Type")          // Label for the input field
        ->placeholder("Offering Type 2")          // Placeholder value for the field
        ->type(CustomerInput::TYPE_SELECT)   // Set the input as a select dropdown
        ->setOption("key", "value")               // Set the options that will be in the dropdown               
        ->setOption("key2", "value2");            // You can set more options for the dropdown
      
    // Add your inputs to your customization object
    $customization
        ->withCustomerInput($textInput)
        ->withCustomerInput($selectInput);
     
    // Now create a payment object and customize it with the customization.
    $payment = new Payment();
    $payment->amount(1.0)
        ->email($email)
        ->phone($phoneNumber)
        ->name($name)
        ->reference(bin2hex(random_bytes(5)))
        ->description("Enter description for the payment")
        ->redirectUrl("https://your/redirect/url")
        ->callbackUrl("https://your/callback_or_webhook/url")
        ->otherInfo("Any extra information")
        ->customize($customization);    // Add the customization you created
    
    // Create the PayFluid client instance.
    $payfluid = new PayFluid($apiId, $apiKey, $loginParameter, $testOrLiveMode);
    
    // Generate credentials.
    // Remember the returned $credentials object here has your session value.
    // It is a good idea to store this value because you will need it to verify payments later.
    $credentials = $payfluid->getSecureCredentials($phoneNumber);
    
    // Get payment link.
    // Again the $paymentLink also has both your 'session' and 'payReference' values.
    // You will need them later for verification.
    $paymentLink = $payfluid->getPaymentLink($credentials, $payment);
    
    // Redirect the user to your customized payment page.
    $paymentLink->webUrl;
} catch (\Throwable $e) {
    // Handle error
    echo "Generating payment url failed: " . $e->getMessage();
}
```

<br>

## ✌️️ Tips

### 1. Pass and retrieve session value from redirect or callback url.
If you are finding it difficult to store your session value you can pass it via your redirect or callback url.  

```php
<?php

require("vendor/autoload.php");

use Gitplus\PayFluid\PayFluid;

$payfluid = new PayFluid($apiId, $apiKey, $loginParameter, $testOrLiveMode);
$credentials = $payfluid->getSecureCredentials($phone);

$payment = new Payment();
$payment->amount(1)
    ->description("Payment for something awesome")
    ->email($email)
    ->phone($phone)
    ->name($name)
    ->otherInfo("Some additional information here")
    ->reference(bin2hex(random_bytes(5)))
    ->redirectUrl("https://your/redirect/url/with/session/appended?session=" . $credentials->session)
    ->callbackUrl("https://your/callback/url/with/session/appended?session=" . $credentials->session);

// You can later retrieve them when the redirect or callback hits your endpoint.
$session = $_GET["session"];
```

<br>

## ⚠️ Issues
If you come across any issue or a problem you can kindly [report it here](https://github.com/gitplus-apps/payfluid/issues)

<br>

## 👊 Contributions
Contributions and improvements are welcome