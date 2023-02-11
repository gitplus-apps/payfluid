# A simple package that wraps [PayFluid's payment API](https://documenter.getpostman.com/view/1587357/SWDzdLcg#3b4e3a30-4714-4d21-a53a-1ca938618ede)

## ⏳ INSTALLATION

---
You will need composer to install this package. You can [get it here](https://getcomposer.org/)
```bash
composer require gitplus/payfluid
```
<br>

## 👼 BASIC USAGE

---
> #### NB: Please note that the IP address of your host device (where you are making requests from) must be whitelisted by PayFluid for any of these to work.  

> #### Kindly note that error handling has been intentionally left out of these examples for brevity.
### 1. Generate payment link.
Here a quick start to get you going quickly.
```php
<?php

require("vendor/autoload.php");

use Gitplus\Payfluid\PayFluid;
use Gitplus\Payfluid\Payment;


try {
    // Create a new PayFluid client instance.
    $payfluid = new PayFluid($apiId, $apiKey, $loginParameter, $testMode);
    
    // Get secure credentials to authenticate with the server.
    // The returned $credentials object here has your 'session' value.
    // It is a good idea to store it for later use. You will need it to
    // verify payments.
    $credentials = $payfluid->getSecureCredentials($phoneNumber);
    
    // Instantiate a new payment object and set the required and any optional fields.
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
    
    // Use both the payment object and secure credentials to get a payment link.
    $paymentLink = $payfluid->getPaymentLink($credentials, $payment);
    
    // You can then retrieve the web url and redirect your user to that location.
    // 
    // NOTE:
    // The $paymentLink object will also have your 'session' and 'payReference' values.
    // It is a good idea to store these values for later. You will need them to
    // verify payments or to retrieve the status of a particular payment later
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

use Gitplus\Payfluid\PayFluid;

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    try {
        // The request from PayFluid to your redirect URL will come with a 'qs' query
        // parameter. Use the 'qs' parameter and your session value to verify the
        // payment.
        //
        // You can get your session value from two(2) places:
        //      1. From the $credentials object you got when you called the getSecureCredentials() method.
        //      2. From the $paymentLink object you got when you called the getPaymentLink() method
        //      It is a good idea to have stored the session value somewhere earlier.
        //
        // Throws an exception if anything goes wrong.
        // Don't worry about calling urldecode() on the 'qs' query parameter. It is handled internally.
        // The $transactionDetails object returned will have details on whether the transaction was successful or not.
        $transactionDetails = PayFluid::verifyPayment($_GET["qs"], $session);
    } catch (\Throwable $e) {
        echo "Verifying payment failed: " . $e->getMessage();
    }   
}
```

### 3. Verify transaction on your callback/webhook URL.
Here is how you can verify a payment when the details get sent to your
callback/webhook url.
```php
require("vendor/autoload.php");

use Gitplus\Payfluid\PayFluid;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Read JSON body from request
        $payload = json_decode(file_get_contents("php://input"));
    
        // NOTE:
        // The $session is from either your $secureCredentials or $paymentLink objects created earlier.
        // The $transactionDetails object returned has details on the success or otherwise of the payment.
        $transactionDetails = PayFluid::verifyPayment($payload, $session);
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

use Gitplus\Payfluid\PayFluid;
use Gitplus\Payfluid\Payment;


try {
    // Create a new PayFluid client instance.
    $payfluid = new PayFluid($apiId, $apiKey, $loginParameter, $testMode);
    
    // This will return a PaymentStatus object with details information on the status of the payment.
    //
    // NOTE:
    // The $payReference is from the $paymentLink object you created earlier.
    // The $session is from either the $paymentLink or $credentials objects you created earlier.
    $paymentStatus = $payfluid->getPaymentStatus($payReference, $session);
} catch (\Throwable $e) {
    // Handle error
    echo "Getting payment status failed: " . $e->getMessage();
}
````
<br>

## 👷 ADVANCED USAGE

---
### 1. Extra required fields
Here are details about some extra required fields. These fields are required
but they have default fields so you can ignore them.
```php
<?php

require("vendor/autoload.php");

use Gitplus\Payfluid\PayFluid;
use Gitplus\Payfluid\Payment;


try {
    // Create a new PayFluid client instance.
    $payfluid = new PayFluid($apiId, $apiKey, $loginParameter, $testMode);
    
    // Get secure credentials for subsequent requests to the API.
    // The returned $credentials object here has your 'session' value.
    // It is a good idea to store it for later use. You will need it to
    // verify payments later.
    $credentials = $payfluid->getSecureCredentials($phoneNumber);
    
    // Instantiate a new payment object and set the required and any optional fields.
    $payment = new Payment();
    
    // These fields are absolutely required.
    $payment->amount(1.0)
        ->email($email)
        ->phone($phoneNumber)
        ->name($name)
        ->reference(bin2hex(random_bytes(5)));
        ->redirectUrl("https://your/redirect/url")
  
    // These fields are also required, but they have default values, so you can ignore them.
    // The default values will be used if you don't set them.
    // If you do NEED to change them, you can do so.
    // Here they with their default values:
    $payment->currency("GHS")
        ->lang("en");
    
    // Optional fields
    $payment->description("Enter description for the payment")
        ->callbackUrl("https://your/callback_or_webhook/url");
        ->otherInfo("Any extra information");
    
    // Use both the payment object and secure credentials to get a payment link.
    $paymentLink = $payfluid->getPaymentLink($credentials, $payment);
    
    // You can then retrieve the payment url and redirect your user to that location.
    // This $paymentLink object will have your 'session' and 'payReference' value.
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

use Gitplus\Payfluid\PayFluid;
use Gitplus\Payfluid\Payment;
use Gitplus\PayFluid\Customization;
use Gitplus\PayFluid\CustomerInput;


try {    
    // Create a new customization object.
    $customization = new Customization();
    $customization->amountIsEditable(true)      // The payment amount can be edited by the user
        ->minimumAmount(1.0)                    // Enforce the minimum amount to pay
        ->maximumAmount(30.0)                   // Enforce the maximum amount to pay
        ->borderTheme("#a3ffee")                // Set a color for the page
        ->receiptMessage("Thank you for your purchase")     // Override the message sent in receipt
        ->receiptFeedbackPhone("+233XXXXXXX")               // Override the phone number that gets the receipt
        ->receiptFeedbackEmail("user@domain.com")           // Override the email that receives the receipt
        ->daysUntilLinkExpires(3)                           // Determine how long the payment link should be valid for
        ->canPayMultipleTimes(true);                        // Payment links are one time. This will make the link reusable
        ->displayPicture("https://link/to/publicly/accessible/image");  // Set your own image to be displayed on the payment page.
        
  
    // You can take your customization further.
    // PayFluid gives you the flexibility to even ask for more information on the payment page.
    // You do this by creating input fields. The fields will be rendered
    // on the payment page for the customer to provide answers to. To achieve this
    // you need to create CustomerInput objects and add them to your customization object.
    
    // Let's create our first input. This will be a text input.
    $textInput = new CustomerInput();
    $textInput->label("Church Membership ID")    // The label for the input
        ->placeholder("Membership ID #")              // The placeholder for the input
        ->inputType(CustomerInput::INPUT_TYPE_TEXT)   // The type of input
        ->required(true);                             // Indicate whether the input is required or not.
        
    
    // Let's create another input. This will be a select dropdown.
    $selectInput = new CustomerInput();
    $selectInput->label("Offering Type")                // Label for the input field
        ->placeholder("Offering Type 2")                // Placeholder value for the field
        ->inputType(CustomerInput::INPUT_TYPE_SELECT)   // Set the input as a select dropdown
        ->setOption("key", "value")                     // Set the options that will be in the dropdown               
        ->setOption("key2", "value2");                  // You can set more options for the dropdown
      
      
    // Add your inputs to your customization object
    $customization
        ->withCustomerInput($textInput)
        ->withCustomerInput($selectInput);
     
     
    // Now let's create our payment object and add our customization.
    // Instantiate a new payment object
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
        ->withCustomization($customization);    // Add the customization you created
    
     
    // Create the PayFluid client instance.
    $payfluid = new PayFluid($apiId, $apiKey, $loginParameter, $testMode);
    
    // Let's generate credentials.
    // Remember the returned $credentials object here has your session value.
    // It is a good idea to store this value because you will need it to verify payments later.
    $credentials = $payfluid->getSecureCredentials($phoneNumber);
    
    // Get payment link. Again the $paymentLink also has both your 'session'
    // and 'payReference' values. You will need them later for verification.
    $paymentLink = $payfluid->getPaymentLink($credentials, $payment);
    
    // Redirect your user to the payment page.
    $paymentLink->webUrl;
} catch (\Throwable $e) {
    // Handle error
    echo "Generating payment url: " . $e->getMessage();
}
```

<br>

## ⚠️ ISSUES

---
If you come across any issue or a problem you can kindly [report it here](https://github.com/gitplus-apps/payfluid/issues)

<br>

## 👊 CONTRIBUTIONS

---
Contributions and improvements are welcomed wholeheartedly.