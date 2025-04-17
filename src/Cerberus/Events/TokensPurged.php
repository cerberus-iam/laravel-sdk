<?php

namespace Cerberus\Events;

class TokensPurged
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public ?string $clientId = null
    ) {}
}
