<?php

namespace Cerberus\Events;

class AccessTokenRevoked
{
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(
        public string $tokenId,
    ) {}
}
