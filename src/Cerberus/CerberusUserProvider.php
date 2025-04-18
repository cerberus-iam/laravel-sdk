<?php

namespace Cerberus;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Throwable;

class CerberusUserProvider implements UserProvider
{
    /**
     * Create a new provider instance and configure the access token.
     *
     * @return void
     */
    public function __construct(protected Cerberus $cerberus)
    {
        $this->cerberus->configureAccessToken();
    }

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return mixed|null
     */
    public function retrieveById($identifier)
    {
        $this->cerberus->configureAccessToken();

        return $this->cerberus->users()->find($identifier);
    }

    /**
     * Retrieve a user by their token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return mixed|null
     */
    public function retrieveByToken($identifier, $token)
    {
        $this->cerberus->useToken($token);

        return $this->cerberus->auth()->findByToken($token);
    }

    /**
     * Update the "remember me" token for the given user.
     *
     * @param  string  $token
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $this->cerberus->configureAccessToken();

        $this->cerberus
            ->users()
            ->where($user->getAuthIdentifierName(), $user->getAuthIdentifier())
            ->update(['remember_token' => $token]);
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array<string, string>  $credentials
     * @return mixed|null
     */
    public function retrieveByCredentials(#[\SensitiveParameter] array $credentials)
    {
        // Filter out password fields from query
        $filters = array_filter(
            $credentials,
            fn ($key) => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($filters)) {
            return null;
        }

        try {
            if (isset($credentials['email'], $credentials['password'])) {
                $this->cerberus->requestAccessTokenWithPassword([
                    'email' => $credentials['email'],
                    'password' => $credentials['password'],
                ]);
            } else {
                $this->cerberus->configureAccessToken();
            }

            $query = $this->cerberus->users();

            foreach ($filters as $key => $value) {
                match (true) {
                    is_array($value) => $query->whereIn($key, $value),
                    $value instanceof Closure => $value($query),
                    default => $query->where($key, $value),
                };
            }

            return $query->first();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Validate a user against given credentials.
     *
     * @param  array<string, string>  $credentials
     */
    public function validateCredentials(
        Authenticatable $user,
        #[\SensitiveParameter]
        array $credentials
    ): bool {
        if (empty($credentials['password'])) {
            return false;
        }

        try {
            return $this->cerberus
                ->auth()
                ->user($user)
                ->checkPassword($credentials);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Optionally rehash the user's password if required.
     *
     * @param  array<string, string>  $credentials
     */
    public function rehashPasswordIfRequired(
        Authenticatable $user,
        #[\SensitiveParameter]
        array $credentials,
        bool $force = false
    ): bool {
        try {
            return $this->cerberus
                ->auth()
                ->user($user)
                ->rehashPasswordIfRequired($credentials, $force);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Get the underlying Cerberus connection instance.
     */
    public function getConnection(): Cerberus
    {
        return $this->cerberus;
    }
}
