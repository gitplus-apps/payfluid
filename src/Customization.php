<?php
declare(strict_types=1);

namespace Gitplus\PayFluid;

use Exception;

class Customization
{
    /**
     * The maximum number of customer inputs allowed for your customizations.
     *
     */
    private const MAX_CUSTOMER_INPUTS = 3;

    /**
     * This gives the payer of a payment link the opportunity to change and
     * decide the amount to pay.
     *
     * @var bool
     */
    private bool $editAmount;

    /**
     * Set and enforce a minimum amount that can be paid by the payer.
     *
     * @var float
     */
    private float $minAmount;

    /**
     * Set and enforce a maximum amount that can be paid by the payer.
     *
     * @var float
     */
    private float $maxAmount;

    /**
     * A CSS hexadecimal color code. Used to override and customize the colour
     * theme of the payment page seen by the payer.
     * E.g. #9c27b0
     *
     * @var string
     */
    private string $borderTheme;

    /**
     * Used to override and customize the receipt message that is displayed to
     * the payer upon successful payment.
     *
     * @var string
     */
    private string $receiptMsg;

    /**
     * Used to override and customize the merchant's phone number to be
     * displayed on the receipt.
     *
     * @var string
     */
    private string $receiptFeedbackPhone;

    /**
     * Used to override and customize the merchant's email address to be
     * displayed on the receipt.
     *
     * @var string
     */
    private string $receiptFeedbackEmail;

    /**
     * @var int
     *
     * Used to override and customize the number of days generated payment link
     * should remain active before it is expired by the system.
     * The default is 3 days.
     */
    private int $payLinkExpiryInDays;

    /**
     * Used to override the default behaviour of a generated payment link that
     * gets closed by the system after a successful payment attempt. Setting
     * this option to true, leaves the payment link open to accept multiple
     * payment request from different payers. This is particularly useful
     * for use cases that involves donation, church payments etc.
     *
     * @var bool
     */
    private bool $canPayMultipleTimes;

    /**
     * A publicly available URL to a picture.
     *
     * Used to override and customise the default display picture setup for merchant
     * or clients. This should be a URL to a picture .This personifies the PayFluid
     * payment page and makes payer comfortable to pay the sender of the payment
     * link. This is useful for cases of remittance from abroad or to receive
     * payment for goods and services rendered locally.
     *
     * @var string
     */
    private string $displayPicture;


    /**
     * This field is used to collect extra information from the payer of a payment
     * link. Up to 3 different information can be requested. It is a json array
     * object signifying the properties of what is to be seen and collected.
     *
     * @var CustomerInput[]
     */
    private array $customerInputs;


    /**
     * This gives the payer of a payment link the opportunity to change and
     * decide the amount to pay.
     *
     * @param bool $isEditable
     * @return $this
     */
    public function editAmount(bool $isEditable): self
    {
        $this->editAmount = $isEditable;
        return $this;
    }

    /**
     * Set and enforce a minimum amount that can be paid by the payer.
     *
     * @param float $minAmount
     * @return $this
     */
    public function minimumAmount(float $minAmount): self
    {
        $this->minAmount = $minAmount;
        return $this;
    }

    /**
     * Set and enforce a maximum amount that can be paid by the payer.
     *
     * @param float $maxAmount
     * @return $this
     */
    public function maximumAmount(float $maxAmount): self
    {
        $this->maxAmount = $maxAmount;
        return $this;
    }

    /**
     * A CSS hexadecimal color code. Used to override and customize the colour
     * theme of the payment page seen by the payer.
     * E.g. #9c27b0
     *
     * @param string $hexColorCode
     * @return $this
     */
    public function borderTheme(string $hexColorCode): self
    {
        $this->borderTheme = trim($hexColorCode);
        return $this;
    }

    /**
     * Used to override and customize the receipt message that is displayed to
     * the payer upon successful payment.
     *
     * @param string $msg
     * @return $this
     */
    public function receiptMessage(string $msg): self
    {
        $this->receiptMsg = trim($msg);
        return $this;
    }

    /**
     * Used to override and customize the merchant's phone number to be
     * displayed on the receipt.
     *
     * @param string $phone
     * @return $this
     * @throws Exception
     */
    public function receiptFeedbackPhone(string $phone): self
    {
        $phone = trim($phone);
        if (strlen($phone) < 10) {
            throw new Exception(
                sprintf("customization: phone number cannot be less than 10 digits; the supplied phone number is %d digits long", strlen($phone))
            );
        }
        if (!is_numeric($phone)) {
            throw new Exception(sprintf("customization: '%s' is not a valid phone number: only digits allowed", $phone));
        }
        $this->receiptFeedbackPhone = $phone;
        return $this;
    }

    /**
     * Used to override and customize the merchant's email address to be
     * displayed on the receipt.
     *
     * @param string $email
     * @return $this
     * @throws Exception
     */
    public function receiptFeedbackEmail(string $email): self
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception(sprintf("customization: email '%s' is not valid", $email));
        }
        $this->receiptFeedbackEmail = $email;
        return $this;
    }

    /**
     * Used to override and customize the number of days the generated payment link
     * should remain active before it is expired by the system.
     * The default is 3 days.
     *
     * @param int $days
     * @return $this
     */
    public function daysUntilLinkExpires(int $days = 3): self
    {
        $this->payLinkExpiryInDays = $days;
        return $this;
    }


    /**
     * A publicly available URL to a picture.
     *
     * Used to override and customise the default display picture setup for merchant
     * or clients. This should be a URL to a picture. This personifies the PayFluid
     * payment page and makes payer comfortable to pay the sender of the payment
     * link. This is useful for cases of remittance from abroad or to receive
     * payment for goods and services rendered locally.
     *
     * @param string $imageUrl
     * @return $this
     * @throws Exception
     */
    public function displayPicture(string $imageUrl): self
    {
        $imageUrl = trim($imageUrl);
        if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            throw new Exception("customization: invalid redirect url");
        }
        $this->displayPicture = $imageUrl;
        return $this;
    }

    /**
     * Used to override the default behaviour of a generated payment link.
     *
     * Usually a payment link gets invalidated or closed by the system
     * after a successful payment attempt. Setting this option to true,
     * leaves the payment link open to accept multiple payment request
     * from different payers. For instance, if you want to collect
     * dues, fees from a group, you can send this same link to
     * all members of the group for them to pay.
     *
     * @param bool $canPayMultiple
     * @return $this
     */
    public function canPayMultipleTimes(bool $canPayMultiple): self
    {
        $this->canPayMultipleTimes = $canPayMultiple;
        return $this;
    }

    /**
     * This is used to ask the customer for extra information.
     *
     * Up to 3 different information can be requested.
     *
     * @param CustomerInput $customerInput
     * @return $this
     * @throws Exception
     */
    public function withCustomerInput(CustomerInput $customerInput): self
    {
        if (
            isset($this->customerInputs) &&
            count($this->customerInputs) === self::MAX_CUSTOMER_INPUTS
        ) {
            throw new Exception(sprintf("customization: maximum number of '%d' extra customer inputs reached", self::MAX_CUSTOMER_INPUTS));
        }

        $this->customerInputs[] = $customerInput;
        return $this;
    }

    /**
     * Returns the entire customization as an array
     *
     * @return array
     * @throws Exception
     */
    public function toArray(): array
    {
        $payload = [
            "borderTheme" => $this->borderTheme,
            "displayPicture" => $this->displayPicture,
            "editAmt" => $this->editAmount,
            "maxAmt" => $this->maxAmount,
            "minAmt" => $this->minAmount,
            "receiptFeedbackEmail" => $this->receiptFeedbackEmail,
            "receiptFeedbackPhone" => $this->receiptFeedbackPhone,
            "receiptSxMsg" => $this->receiptMsg,
            "payLinkCanPayMultipleTimes" => $this->canPayMultipleTimes,
            "payLinkExpiryInDays" => $this->payLinkExpiryInDays,
        ];

        if (!empty($this->customerInputs)) {
            $customerInputs = [];

            foreach ($this->customerInputs as $input) {

                // You cannot have a select input type without any options
                if (
                    $input->getType() === CustomerInput::TYPE_SELECT &&
                    count($input->getOptions()) === 0
                ) {
                    throw new Exception(sprintf("customization: CustomerInput with type '%s' must have at least one option set", CustomerInput::TYPE_SELECT));
                }

                $customerInputs[] = [
                    "label" => $input->getLabel(),
                    "options" => $input->getOptions(),
                    "placeholder" => $input->getPlaceholder(),
                    "required" => $input->getRequired(),
                    "type" => $input->getType(),
                ];
            }

            $payload["xtraCustomerInput"] = $customerInputs;
        }

        ksort($payload);
        return $payload;
    }

    /**
     * Returns a JSON representation of this customization
     *
     * @return string
     * @throws Exception
     */
    public function getJsonEncoded(): string
    {
        $payload = $this->toArray();
        $encoding = json_encode($payload);
        if ($encoding === false) {
            throw new Exception("customization: failed to json encode customization: " . json_last_error_msg());
        }
        return $encoding;
    }
}
