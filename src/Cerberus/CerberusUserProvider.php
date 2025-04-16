<?php

namespace Cerberus;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Throwable;

class CerberusUserProvider implements UserProvider
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected Cerberus $cerberus)
    {
        // Initialize with client credentials token by default
        $this->cerberus->configureAccessToken();
    }

    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById($identifier)
    {
        // Ensure we have a valid token before making the request
        $this->cerberus->configureAccessToken();

        return $this->cerberus->users()->find($identifier);
    }

    /**
     * Retrieve a user by their token.
     */
    public function retrieveByToken($identifier, $token)
    {
        // Use the token directly instead of just setting it for one request
        $this->cerberus->useToken($token);

        return $this->cerberus->auth()->findByToken($token);
    }

    /**
     * Update the "remember me" token for the user.
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        // Ensure we have a valid token before making the request
        $this->cerberus->configureAccessToken();

        // Get the user identifier name and value
        $identifierName = $user->getAuthIdentifierName();
        $identifierValue = $user->getAuthIdentifier();

        // Update the remember token
        $this->cerberus
            ->users()
            ->where($identifierName, $identifierValue)
            ->update(['remember_token' => $token]);
    }

    /**
     * Retrieve a user by the given credentials.
     */
    public function retrieveByCredentials(array $credentials)
    {
        // Store original credentials including password
        $originalCredentials = $credentials;

        // Filter out password for querying
        $queryCredentials = array_filter(
            $credentials,
            fn ($key) => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($queryCredentials)) {
            return null;
        }

        try {
            // If we have email and password, request a token using password grant
            if (isset($originalCredentials['email'], $originalCredentials['password'])) {
                // Request token using password grant
                $this->cerberus->requestAccessTokenWithPassword([
                    'email' => $originalCredentials['email'],
                    'password' => $originalCredentials['password'],
                ]);
            } else {
                // Ensure we have a client credentials token for this request
                $this->cerberus->configureAccessToken();
            }

            // Build query using filtered credentials (without password)
            $query = $this->cerberus->users();

            foreach ($queryCredentials as $key => $value) {
                if (is_array($value)) {
                    $query->whereIn($key, $value);
                } elseif ($value instanceof Closure) {
                    $value($query);
                } else {
                    $query->where($key, $value);
                }
            }

            return $query->first();
        } catch (Throwable $e) {
            // Log the error if necessary
            // logger()->error('Failed to retrieve user by credentials', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Validate a user against the given credentials.
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if (! isset($credentials['password'])) {
            return false;
        }

        try {
            // The token should already be configured from retrieveByCredentials
            return $this->cerberus
                ->auth()
                ->user($user)
                ->checkPassword($credentials);
        } catch (Throwable $th) {
            // Log the error if necessary
            // logger()->error('Password validation failed', ['error' => $th->getMessage()]);
            return false;
        }
    }

    /**
     * Optionally rehash password if required (e.g., algorithm update).
     */
    public function rehashPasswordIfRequired(
        Authenticatable $user,
        array $credentials,
        bool $force = false
    ) {
        try {
            return $this->cerberus
                ->auth()
                ->user($user)
                ->rehashPasswordIfRequired($credentials, $force);
        } catch (Throwable $e) {
            // Silently fail as this is a non-critical operation
            return false;
        }
    }

    /**
     * Get the Cerberus client instance.
     */
    public function getConnection(): Cerberus
    {
        return $this->cerberus;
    }
}
