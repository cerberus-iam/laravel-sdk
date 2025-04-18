<?php

namespace Cerberus\Resources;

use Carbon\Carbon;
use DateTimeImmutable;

class Token extends Resource
{
    /**
     * Create a new Token resource instance.
     */
    public function __construct(array $attributes = [])
    {
        $this->parseExpiresIn($attributes);

        parent::__construct($attributes);
    }

    /**
     * Get the token expiration timestamp.
     */
    public function expiresAt(): ?Carbon
    {
        $timestamp = $this->getAttribute('expires_in');

        return $timestamp instanceof Carbon
            ? $timestamp
            : Carbon::parse($timestamp);
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt()?->isPast() ?? true;
    }

    /**
     * Parse the expires in attribute from date-time immutable to carbon instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function parseExpiresIn(array &$attributes): void
    {
        $expiresIn = $attributes['expires_in'] ?? null;

        if (! $expiresIn) {
            return;
        }

        if ($expiresIn instanceof DateTimeImmutable) {
            $attribute['expires_in'] = Carbon::instance($expiresIn);
        }
    }
}
