<?php

declare(strict_types=1);

namespace CerberusIAM\Support\Stores;

use CerberusIAM\Contracts\TokenStore;
use Illuminate\Contracts\Session\Session;

/**
 * Session-based Token Store
 *
 * This class implements the TokenStore interface using Laravel's session storage
 * to persist OAuth tokens during the user's session.
 */
class SessionTokenStore implements TokenStore
{
    /**
     * Create a new session token store instance.
     *
     * @param  Session  $session  The Laravel session instance.
     * @param  string  $key  The session key to store tokens under.
     */
    public function __construct(
        protected Session $session,
        protected string $key = 'cerberus.tokens'
    ) {}

    /**
     * Store the token payload in the session.
     *
     * This method stores the token data in the Laravel session using the configured key.
     *
     * @param  array<string, mixed>  $payload  The token data to store.
     */
    public function store(array $payload): void
    {
        // Store the token payload in the session using the configured key
        $this->session->put($this->key, $payload);
    }

    /**
     * Retrieve the stored tokens from the session.
     *
     * This method retrieves the token data from the session, ensuring it is an array.
     *
     * @return array<string, mixed>|null The stored token data, or null if not found or invalid.
     */
    public function retrieve(): ?array
    {
        // Get the tokens from the session
        $tokens = $this->session->get($this->key);

        // Return the tokens if they are an array, otherwise null
        return is_array($tokens) ? $tokens : null;
    }

    /**
     * Clear all stored tokens from the session.
     *
     * This method removes the token data from the session.
     */
    public function clear(): void
    {
        // Remove the tokens from the session
        $this->session->forget($this->key);
    }
}
