<?php

declare(strict_types=1);

namespace Gitplus\PayFluid;

use DateTime;
use Exception;


class Payment
{
    /**
     * Specifies the maximum number of characters in a payment description
     *
     * @var string
     */
    private const MAX_DESCRIPTION_LEN = 40;

    /**
     * Sets the maximum number of characters in a payment reference.
     *
     * @var string
     */
    private const MAX_REFERENCE_LEN = 10;

    /**
     * List of allowed lang values
     *
     * @var string[]
     */
    private const VALID_LANG_VALUES = [
      "en", "fr",
    ];

    /**
     * The exact transaction amount value expected to be charged the customer.
     *
     * @var float
     */
    private float $amount;

    /**
     * The ISO currency code. e.g. GHS. Defaults to 'GHS'.
     *
     * @var string
     */
    private string $currency;

    /**
     * The date time transaction was created and sent by the client.
     * Its format is yyyy-MM-ddThh:mm:ss.SSSZ
     *
     * @var string
     */
    private string $dateTime;

    /**
     * The narration or description of the purchase made on client store or cart.
     * It should not be more than 40 characters.
     *
     * @var string
     */
    private string $description;

    /**
     * The email of customer as maintained on client platform
     *
     * @var string
     */
    private string $email;

    /**
     * The language in which the payment platform should serve the customer.
     * Acceptable values include "en", "fr". Defaults to "en".
     *
     * @var string
     */
    private string $lang;

    /**
     * The mobile number of customer as maintained on client platform.
     * This is preferred in international format.
     *
     * @var string
     */
    private string $phone;

    /**
     * The names of customer making the purchase.
     *
     * @var string
     */
    private string $name;

    /**
     * Any other information the client want to be sent to the payment platform.
     *
     * @var string
     */
    private string $otherInfo;

    /**
     * An alphanumeric identification and tracing value associated to the customer's
     * transaction. It should not be more than 10 characters, and it's expected
     * to be unique when sent to the payment server.
     *
     * @var string
     */
    private string $reference;

    /**
     * This is the client URL where the payment server would redirect to in order
     * to deliver the status of customer's transaction. It is expected that the
     * details of the feedback would be verified and used to issue receipt
     * or purchase value to customer.
     * @var string
     */
    private string $redirectUrl;

    /**
     * This is the client backend URL (aka webhook) that the payment server would
     * push the transaction status to. This serves the purpose of double PUSH
     * confirmation and also for payments that were done out of trackable
     * session on client platform. It should be different from "responseRedirectURL".
     *
     * @var string
     */
    private string $callbackUrl;

    /**
     * This field exposes the power, dynamism and flexibility of PayFluid platform
     * by handing over control to the integrator as to how the behaviour of the
     * payment link generated is to be. Integrator can override much of payment
     * link properties to suit their taste and application use case.
     *
     * The field takes a json parsable object with the following properties;
     *      editAmt:
     *          This gives the payer of a payment link the opportunity to change
     *          and decide the amount to pay.
     *
     *      minAmt:
     *          Integrator can set and enforce a minimum amount that can be paid by the payer.
     *
     *      maxAmt:
     *          Integrator can set and enforce a maximum amount that can be paid by the payer.
     *
     *      borderTheme:
     *          Used to override and customise the colour theme of the payment page seen by the payer.
     *
     *      receiptSxMsg:
     *          Used to override and customise the receipt message that is displayed
     *          to the payer upon successful payment.
     *
     *      receiptFeedbackPhone:
     *          Used to override and customise the merchant's phone number to be
     *          displayed on the receipt.
     *
     *      receiptFeedbackEmail:
     *          Used to override and customise the merchant's email address to be
     *          displayed on the receipt.
     *
     *      payLinkExpiryInDays:
     *          Used to override and customise the number of days generated payment
     *          link should remain active before its expired by the system.
     *          The default is 3 days.
     *
     *      payLinkCanPayMultipleTimes:
     *          Used to override the default behaviour of a generated payment link
     *          that gets closed by the system after a successful payment attempt.
     *          Setting this option to true, leaves the payment link open to
     *          accept multiple payment request from different payers. This
     *          is particularly useful for use cases that involves
     *          donation, church payments etc.
     *
     *      displayPicture:
     *          Used to override and customise the default display picture setup
     *          for merchant or clients. This personifies the PayFluid payment
     *          page and makes payer comfortable to pay the sender of the
     *          payment link. This is useful for cases of remittance
     *          from abroad or to receive payment for goods and
     *          services rendered locally.
     *
     *      xtraCustomerInput:
     *          This field is used to collect extra information from the payer of
     *          a payment link. Up to 3 different information can be requested.
     *          It is a json array object signifying the properties of what
     *          is to be seen and collected.
     *
     *      Example:
     *      {
     *          "editAmt":true,
     *          "minAmt":5.00,
     *          "maxAmt":1500.50,
     *          "borderTheme":"#9c27b0",
     *          "receiptSxMsg":"Please login to www.nestle.com, type in your payment reference number to download your order form. Contact the below number if there are any challenges",
     *          "receiptFeedbackPhone":"233241234567",
     *          "receiptFeedbackEmail":"order@nestle.com",
     *          "payLinkExpiryInDays":15,
     *          "payLinkCanPayMultipleTimes":true,
     *          "displayPicture":"https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSQbozpnBbX46cLB7OrTw977TArZ1jIDExRBolJxBXf8dVMjSId--SxEA",
     *          "xtraCustomerInput": [
     *              {
     *                  "label":"Church Membership ID",
     *                  "placeHolder":"Membership ID #",
     *                  "type":"input",
     *                  "options":[{"k":"","v":""}],
     *                  "required":true
     *              },
     *              {
     *                  "label":"Offering Type",
     *                  "placeHolder":"Offering Type2",
     *                  "type":"select",
     *                  "options": [
     *                      {
     *                          "k":"firstFruit",
     *                          "v":"First Fruit"
     *                      },
     *                      {
     *                          "k":"tithe",
     *                          "v":"Tithe"
     *                      },
     *                      {
     *                          "k":"harvest",
     *                          "v":"Harvest"
     *                      }
     *                  ],
     *                  "required":true
     *              }
     *          ]
     *    }
     * @var Customization
     */
    private Customization $pageCustomization;

    public function __construct()
    {
        $this->currency = "GHS";
        $this->lang = "en";

        $now = new DateTime();
        $this->dateTime = $now->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * Sets the amount to charge.
     *
     * @param float $amount
     * @return $this
     * @throws Exception
     */
    public function amount(float $amount): self
    {
        if (!filter_var($amount, FILTER_VALIDATE_FLOAT)) {
            throw new Exception(sprintf("validate payment: '%s' amount is not valid float or decimal", $amount));
        }
        $this->amount = $amount;
        return $this;
    }

    /**
     * Sets the currency for the payment.
     *
     * Defaults to 'GHS' so you can decide not to call this method.
     *
     * @param string $currency
     * @return $this
     */
    public function currency(string $currency = "GHS"): self
    {
        $this->currency = trim($currency);
        return $this;
    }

    /**
     * Sets narration or description of the purchase being made.
     * It should not be more than 40 characters.
     *
     * @param string $description
     * @return $this
     * @throws Exception
     */
    public function description(string $description): self
    {
        $description = trim($description);
        if (strlen($description) > self::MAX_DESCRIPTION_LEN) {
            throw new Exception(sprintf("payment: description cannot be more than %d characters long", self::MAX_DESCRIPTION_LEN));
        }

        $this->description = $description;
        return $this;
    }

    /**
     * Returns the description for this payment.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Sets the email of customer you want to charge.
     * This must be a valid email.
     *
     * @param string $email
     * @return $this
     * @throws Exception
     */
    public function email(string $email): self
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception(sprintf("payment: email '%s' is not valid", $email));
        }
        $this->email = trim($email);
        return $this;
    }

    /**
     * Sets the language in which the payment platform would be displayed to the
     * customer.
     *
     * Acceptable values include "en", "fr". Defaults to "en".
     *
     * @param string $lang
     * @return $this
     * @throws Exception
     */
    public function language(string $lang): self
    {
        $lang = trim($lang);
        if (!in_array($lang,self::VALID_LANG_VALUES)) {
            throw new Exception(sprintf("payment: invalid value for language, expected one of [%s] but got '%s'", join(",", self::VALID_LANG_VALUES), $lang));
        }
        $this->lang = $lang;
        return $this;
    }

    /**
     * Sets the mobile number of the customer being charged.
     * It is preferred in international format.
     * It cannot be less than 10 characters.
     *
     * @param string $phone
     * @return $this
     * @throws Exception
     */
    public function phone(string $phone): self
    {
        $phone = trim($phone);
        if (strlen($phone) < 10) {
            throw new Exception(
                sprintf("payment: phone number cannot be less than 10 digits; the supplied phone number is %d digits long", strlen($phone))
            );
        }
        if (!is_numeric($phone)) {
            throw new Exception(sprintf("payment: '%s' is not a valid phone number: only digits allowed", $phone));
        }
        $this->phone = trim($phone);
        return $this;
    }

    /**
     * Sets the name of the customer making the purchase.
     *
     * @param string $name
     * @return $this
     */
    public function name(string $name): self
    {
        $this->name = trim($name);
        return $this;
    }

    /**
     * Sets any other information you want to be sent to the payment platform.
     *
     * @param string $otherInfo
     * @return $this
     */
    public function otherInfo(string $otherInfo): self
    {
        $this->otherInfo = trim($otherInfo);
        return $this;
    }

    /**
     * Sets the reference for this payment.
     *
     * An alphanumeric identification and tracing value associated to the customer's
     * transaction. It should not be more than 10 characters, and it's expected
     * to be unique when sent to the payment server. You cannot send the same
     * reference twice.
     *
     * @param string $reference
     * @return $this
     * @throws Exception
     */
    public function reference(string $reference): self
    {
        $reference = trim($reference);
        $refLength = strlen($reference);
        if ($refLength > self::MAX_REFERENCE_LEN) {
            throw new Exception(
                sprintf(
                    "payment: reference cannot be more than %d characters: your reference '%s' is %d characters long", self::MAX_REFERENCE_LEN, $reference, $refLength
                )
            );
        }

        $this->reference = $reference;
        return $this;
    }

    /**
     * This is the URL where the payment server would redirect to after payment.
     *
     * This is so PayFluid can deliver the status of the customer's transaction.
     * It is expected that the details of the feedback would be verified and
     * used to issue receipt or purchase value to customer. It should be
     * different from your callback url.
     *
     * @param string $redirectUrl
     * @return $this
     * @throws Exception
     */
    public function redirectUrl(string $redirectUrl): self
    {
        $redirectUrl = trim($redirectUrl);
        if (!filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
            throw new Exception("payment: invalid redirect url");
        }
        if (!empty($this->callbackUrl) && strcmp($this->callbackUrl, $redirectUrl) === 0) {
            throw new Exception("payment: redirect and callback url cannot be the same");
        }
        $this->redirectUrl = $redirectUrl;
        return $this;
    }

    /**
     * This is your backend URL (aka webhook) that the payment server would push
     * the transaction status to.
     *
     * This serves the purpose of double PUSH confirmation and also for payments
     * that were done out of trackable session on client platform.
     * It should be different from your redirect url.
     *
     * @param string $callbackUrl
     * @return $this
     * @throws Exception
     */
    public function callbackUrl(string $callbackUrl): self
    {
        $callbackUrl = trim($callbackUrl);
        if (!filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
            throw new Exception("payment: invalid callback url");
        }
        if (!empty($this->redirectUrl) && strcmp($this->redirectUrl, $callbackUrl) === 0) {
            throw new Exception("payment: callback and redirect url cannot be the same");
        }
        $this->callbackUrl = $callbackUrl;
        return $this;
    }

    /**
     * Customize the payment with your preferred customization.
     *
     * @param Customization $customization
     * @return $this
     */
    public function customize(Customization $customization): self
    {
        $this->pageCustomization = $customization;
        return $this;
    }

    /**
     * Returns the payment's reference.
     *
     * @return string
     */
    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * Returns the amount being charged.
     *
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * Returns the currency for this payment.
     *
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Returns the date and time for this payment.
     *
     * @return string
     */
    public function getDateTime(): string
    {
        return $this->dateTime;
    }

    /**
     * Returns the customization for this object if it has been set.
     *
     * @return Customization|null
     */
    public function customization(): ?Customization
    {
        if (!$this->hasCustomization()) {
            return null;
        }
        return $this->pageCustomization;
    }

    /**
     * Indicates if the payment has a customization.
     *
     * @return bool
     */
    public function hasCustomization(): bool
    {
        return isset($this->pageCustomization);
    }

    /**
     * Returns the payment's customer email
     *
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * Returns the language set for this payment.
     *
     * @return string
     */
    public function getLang(): string
    {
        return $this->lang;
    }

    /**
     * Returns the payment's customer phone number.
     *
     * @return string
     */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * Returns the additional information added to the payment.
     *
     * @return string
     */
    public function getOtherInfo(): string
    {
        return $this->otherInfo;
    }

    /**
     * Returns the customer's name for this payment
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the redirect url set for this payment.
     *
     * @return string
     */
    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    /**
     * Returns the callback/webhook url set for this payment.
     *
     * @return string
     */
    public function getCallbackUrl(): string
    {
        return $this->callbackUrl;
    }

}

