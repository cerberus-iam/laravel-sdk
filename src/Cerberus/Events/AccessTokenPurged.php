<?php

namespace Cerberus\Events;

class AccessTokenPurged
{
    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $tokenId,
        public ?string $userId = null,
        public ?string $clientId = null
    ) {}
}
