<?php

declare(strict_types=1);

namespace CerberusIAM\Auth;

use CerberusIAM\Contracts\IamClient;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * Cerberus User Provider
 *
 * This class implements Laravel's UserProvider interface for Cerberus IAM,
 * providing methods to retrieve users from the IAM service.
 */
class CerberusUserProvider implements UserProvider
{
    /**
     * Create a new Cerberus user provider instance.
     *
     * @param  IamClient  $client  The IAM client for API communication.
     */
    public function __construct(
        protected IamClient $client
    ) {}

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier  The user identifier.
     * @return Authenticatable|null The user instance, or null if not found.
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        // Attempt to get user data by ID from the IAM service
        $payload = $this->client->getUserById($identifier);

        // Create and return a CerberusUser if payload exists
        return $payload ? CerberusUser::fromProfile($payload) : null;
    }

    /**
     * Retrieve a user by their remember token (not supported).
     *
     * @param  mixed  $identifier  The user identifier.
     * @param  string  $token  The remember token.
     * @return Authenticatable|null Always returns null.
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        // Remember tokens are not supported for OAuth authentication
        return null;
    }

    /**
     * Update the remember token for a user (not supported).
     *
     * @param  Authenticatable  $user  The user instance.
     * @param  string  $token  The remember token.
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // Remember tokens are not supported for OAuth authentication
    }

    /**
     * Retrieve a user by credentials (not supported).
     *
     * @param  array<string, mixed>  $credentials  The credentials.
     * @return Authenticatable|null Always returns null.
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        // Password-based authentication is not supported
        return null;
    }

    /**
     * Validate user credentials (not supported).
     *
     * @param  Authenticatable  $user  The user instance.
     * @param  array<string, mixed>  $credentials  The credentials.
     * @return bool Always returns false.
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        // Password validation is not supported for OAuth
        return false;
    }

    /**
     * Rehash the user's password if required (not applicable).
     *
     * @param  Authenticatable  $user  The user instance.
     * @param  array<string, mixed>  $credentials  The credentials.
     * @param  bool  $force  Whether to force rehashing.
     * @return bool Always returns false.
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): bool
    {
        // Password management is delegated to Cerberus IAM, so there is nothing to rehash locally
        return false;
    }
}
