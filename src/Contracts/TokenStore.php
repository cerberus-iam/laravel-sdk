<?php

declare(strict_types=1);

namespace CerberusIAM\Contracts;

/**
 * Token Store Contract
 *
 * This interface defines the contract for storing and retrieving OAuth tokens,
 * including access tokens, refresh tokens, and their associated metadata.
 */
interface TokenStore
{
    /**
     * Persist the token payload.
     *
     * This method stores the token data, including access token, refresh token,
     * expiry time, and any other relevant metadata.
     *
     * @param  array<string, mixed>  $payload  The token data including access_token, refresh_token, expires_at, etc.
     */
    public function store(array $payload): void;

    /**
     * Retrieve the stored tokens.
     *
     * This method retrieves the currently stored token data from the store.
     *
     * @return array<string, mixed>|null The stored token data, or null if no tokens are stored.
     */
    public function retrieve(): ?array;

    /**
     * Clear all stored tokens.
     *
     * This method removes all stored token data from the store.
     */
    public function clear(): void;
}
