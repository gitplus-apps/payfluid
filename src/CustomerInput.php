<?php

namespace Gitplus\PayFluid;

use Exception;

/**
 * CustomerInput is an extra input for collecting information from the payer
 */
class CustomerInput
{
    public const TYPE_TEXT = "input";
    public const TYPE_SELECT = "select";

    private const VALID_INPUT_TYPES = [
        self::TYPE_TEXT,
        self::TYPE_SELECT,
    ];

    private string $label;
    private string $placeholder;
    private string $type;
    private bool $required = false;
    private array $options;

    public function __construct()
    {
        $this->type = $this->placeholder = $this->label = "";
    }

    /**
     * Sets the label for the customer input.
     *
     * @param string $label
     * @return $this
     */
    public function label(string $label): self
    {
        $this->label = trim($label);
        return $this;
    }

    /**
     * Sets the placeholder value for the input.
     *
     * @param string $placeholder
     * @return $this
     */
    public function placeholder(string $placeholder): self
    {
        $this->placeholder = trim($placeholder);
        return $this;
    }

    /**
     * Sets the type of this input.
     *
     * @param string $type
     * @return $this
     * @throws Exception
     */
    public function type(string $type): self
    {
        if (!in_array($type, self::VALID_INPUT_TYPES)) {
            throw new Exception(sprintf("customer input: invalid input type '%s', expected one of (%s)", $type, join(",", self::VALID_INPUT_TYPES)));
        }
        $this->type = $type;
        return $this;
    }

    /**
     * Indicates if the input is required or not.
     *
     * @param bool $required
     * @return $this
     */
    public function required(bool $required): self
    {
        $this->required = $required;
        return $this;
    }

    /**
     * Sets the options for a select input type
     *
     * @param string $key
     * @param string $value
     * @return $this
     * @throws Exception
     */
    public function setOption(string $key, string $value): self
    {
        if (empty($key)) {
            throw new Exception("customer input: set option: key cannot be empty");
        }
        $this->options[] = [
            "k" => trim($key),
            "v" => trim($value),
        ];
        return $this;
    }

    /**
     * Returns the options set for this input.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    /**
     * Returns the type of input.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type ?? "";
    }

    /**
     * Returns the label for this input
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label ?? "";
    }

    /**
     * Returns the placeholder value for this input
     *
     * @return string
     */
    public function getPlaceholder(): string
    {
        return $this->placeholder ?? "";
    }

    /**
     * Lets you know if this input is required or not.
     *
     * @return bool
     */
    public function getRequired(): bool
    {
        return $this->required ?? false;
    }
}