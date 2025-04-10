<?php

namespace Cerberus\Exceptions;

class ResourceValidationException extends ResourceException
{
    /**
     * The validation errors.
     *
     * @var array<string, string[]>
     */
    protected array $errors = [];

    /**
     * Create a new validation exception instance.
     *
     * @param  array<string, string[]>  $errors
     */
    public function __construct(
        string $message,
        array $errors = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->errors = $errors;
    }

    /**
     * Get the validation errors.
     *
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Set the validation errors.
     *
     * @param  array<string, string[]>  $errors
     * @return $this
     */
    public function setErrors(array $errors): self
    {
        $this->errors = $errors;

        return $this;
    }
}
