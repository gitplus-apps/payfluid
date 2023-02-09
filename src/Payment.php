<?php

namespace Gitplus\PayFluid;


class Payment
{
    /**
     * The exact transaction amount value expected to be charged the customer.
     *
     * @var float
     */
    public float $amount;

    /**
     * The ISO currency code. e.g. GHS. Defaults to 'GHS'.
     *
     * @var string
     */
    public string $currency;

    /**
     * The date time transaction was created and sent by the client. Its format is yyyy-MM-ddThh:mm:ss.SSSZ
     *
     * @var string
     */
    public string $dateTime;

    /**
     * The narration or description of the purchase made on client store or cat.
     * It should not be more than 40 characters.
     *
     * @var string
     */
    public string $description;

    /**
     * The email of customer as maintained on client platform
     *
     * @var string
     */
    public string $email;

    /**
     * The language in which the payment platform should serve the customer.
     * Acceptable values include "en", "fr". Defaults to "en".
     *
     * @var string
     */
    public string $lang;

    /**
     * The mobile number of customer as maintained on client platform. This is preferred in international format.
     *
     * @var string
     */
    public string $phone;

    /**
     * The names of customer making the purchase.
     *
     * @var string
     */
    public string $name;

    /**
     * Any other information the client want to be sent to the payment platform.
     *
     * @var string
     */
    public string $otherInfo;

    /**
     * An alphanumeric identification and tracing value associated to the customer's transaction.
     * It should not be more than 10 characters, and it's expected to be unique when sent to the payment server.
     *
     * @var string
     */
    public string $reference;

    /**
     * This is the client URL where the payment server would redirect to in order to deliver the status of customer's transaction.
     * It is expected that the details of the feedback would be verified and used to issue receipt or purchase value to customer.
     * @var string
     */
    public string $responseRedirectUrl;

    /**
     * This is the client backend URL (aka webhook) that the payment server would push the transaction status to.
     * This serves the purpose of double PUSH confirmation and also for payments that were done out of
     * trackable session on client platform. It should be different from "responseRedirectURL".
     *
     * @var string
     */
    public string $trxStatusCallbackURL;

    /**
     * This field exposes the power, dynamism and flexibility of Payfluid platform by handing over control to the
     * integrator as to how the behaviour of the payment link generated is to be. Integrator can override much of
     * payment link properties to suit their taste and application use case.
     *
     * The field takes a json parsable object with the following properties;
     *      editAmt:
     *          This gives the payer of a payment link the opportunity to change and decide the amount to pay.
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
     *          Used to override and customise the receipt message that is displayed to the payer upon successful payment.
     *
     *      receiptFeedbackPhone:
     *          Used to override and customise the merchant's phone number to be displayed on the receipt.
     *
     *      receiptFeedbackEmail:
     *          Used to override and customise the merchant's email address to be displayed on the receipt.
     *
     *      payLinkExpiryInDays:
     *          Used to override and customise the number of days generated payment link should remain
     *          active before its expired by the system. The default is 3 days.
     *
     *      payLinkCanPayMultipleTimes:
     *          Used to override the default behaviour of a generated payment link that gets closed
     *          by the system after a successful payment attempt. Setting this option to true, leaves
     *          the payment link open to accept multiple payment request from different payers.
     *          This is particularly useful for use cases that involves donation, church payments etc.
     *
     *      displayPicture:
     *          Used to override and customise the default display picture setup for merchant or clients.
     *          This personifies the Payfluid payment page and makes payer comfortable to pay the sender
     *          of the payment link. This is useful for cases of remittance from abroad or to receive payment
     *          for goods and services rendered locally.
     *
     *      xtraCustomerInput:
     *          This field is used to collect extra information from the payer of a payment link.
     *          Up to 3 different information can be requested. It is a json array object signifying
     *          the properties of what is to be seen and collected.
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
     *                          "k":"firstfruit",
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
     * @var string
     */
    public string $customTxn = "";

    public function __construct()
    {
        $this->currency = "GHS";
        $this->lang = "en";

        $now = new \DateTime();
        $this->dateTime = $now->format('Y-m-d\TH:i:s.v\Z');
    }
}

