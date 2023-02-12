<?php

namespace Gitplus;


class CustomerInput
{
    public const TYPE_TEXT = "input";
    public const TYPE_SELECT = "select";
    private string $label;
    private string $placeholder;
    private string $type;
    private bool $required = false;
    private array $options;

    /**
     * Sets the label for the customer input.
     *
     * @param string $label
     * @return $this
     */
    public function label(string $label): self
    {
        $this->label = $label;
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
        $this->placeholder = $placeholder;
        return $this;
    }

    /**
     * Sets the type of this input.
     *
     * @param string $type
     * @return $this
     */
    public function inputType(string $type): self
    {
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
     */
    public function setOption(string $key, string $value): self
    {
        $this->options[] = [
            "k" => $key,
            "v" => $value
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
        return $this->type;
    }

    /**
     * Returns the label for this input
     *
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Returns the placeholder value for this input
     *
     * @return string
     */
    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    /**
     * Lets you know if this input is required or not.
     *
     * @return bool
     */
    public function getRequired(): bool
    {
        return $this->required;
    }
}