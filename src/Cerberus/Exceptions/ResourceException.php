<?php

namespace Cerberus\Exceptions;

use DomainException;

class ResourceException extends DomainException
{
    /**
     * The resource that caused the exception.
     */
    protected ?string $resource = null;

    /**
     * Set the resource that caused the exception.
     *
     * @return $this
     */
    public function setResource(string $resource): self
    {
        $this->resource = $resource;

        return $this;
    }

    /**
     * Get the resource that caused the exception.
     */
    public function getResource(): ?string
    {
        return $this->resource;
    }
}
