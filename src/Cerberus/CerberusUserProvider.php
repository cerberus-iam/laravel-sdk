<?php

namespace Cerberus;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Throwable;

/**
 * UserProvider implementation that retrieves user data via the Cerberus IAM API.
 *
 * This class is designed for stateless, token-based authentication using an external API.
 */
class CerberusUserProvider implements UserProvider
{
    /**
     * Create a new CerberusUserProvider instance.
     *
     * @param  Cerberus  $cerberus  The Cerberus API client instance.
     */
    public function __construct(protected Cerberus $cerberus)
    {
        //
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier  The user ID.
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        try {
            return $this->cerberus->users()->find($identifier);
        } catch (Throwable $e) {
            logger()->warning('[CerberusUserProvider] Failed to retrieve user by ID.', [
                'id' => $identifier,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Retrieve a user by their authentication token.
     *
     * @param  mixed  $identifier  Not used.
     * @param  string  $token  The access token.
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        try {
            // Use the token for this request
            $this->cerberus->useToken($token);

            return $this->cerberus->auth()->findByToken($token);
        } catch (Throwable $e) {
            logger()->warning('[CerberusUserProvider] Failed to retrieve user by token.', [
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Update the "remember me" token.
     *
     * Not applicable for stateless authentication; method is a no-op.
     *
     * @param  string  $token
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // No-op: Remember tokens are not used in token-based authentication
    }

    /**
     * Retrieve a user by given credentials (e.g. email).
     *
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        // Remove any password field to avoid matching on it
        $filters = array_filter(
            $credentials,
            fn ($key) => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($filters)) {
            return null;
        }

        try {
            $query = $this->cerberus->users();

            foreach ($filters as $key => $value) {
                match (true) {
                    is_array($value) => $query->whereIn($key, $value),
                    $value instanceof Closure => $value($query),
                    default => $query->where($key, $value),
                };
            }

            return $query->first();
        } catch (Throwable $e) {
            logger()->warning('[CerberusUserProvider] Failed to retrieve user by credentials.', [
                'credentials' => array_keys($credentials),
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Validate a user's credentials against Cerberus.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (empty($credentials['password'])) {
            return false;
        }

        try {
            return $this->cerberus->auth()->user($user)->checkPassword($credentials);
        } catch (Throwable $e) {
            logger()->warning('[CerberusUserProvider] Password validation failed.', [
                'user_id' => $user->getAuthIdentifier(),
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Rehash a user's password if necessary.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function rehashPasswordIfRequired(
        Authenticatable $user,
        array $credentials,
        bool $force = false
    ): bool {
        try {
            return $this->cerberus
                ->auth()
                ->user($user)
                ->rehashPasswordIfRequired($credentials, $force);
        } catch (Throwable $e) {
            logger()->warning('[CerberusUserProvider] Password rehashing failed.', [
                'user_id' => $user->getAuthIdentifier(),
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Get the underlying Cerberus client instance.
     */
    public function getConnection(): Cerberus
    {
        return $this->cerberus;
    }
}
