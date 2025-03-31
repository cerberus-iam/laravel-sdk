<?php

namespace Cerberus\Contracts;

interface TokenStorage
{
    /**
     * Retrieve the current token from storage.
     */
    public function get(): ?array;

    /**
     * Store the token payload in storage.
     */
    public function put(array $data, int $ttlSeconds): void;

    /**
     * Forget the current token from storage.
     */
    public function forget(): void;
}
