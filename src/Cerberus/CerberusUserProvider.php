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
        //
    }

    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById($identifier)
    {
        return $this->cerberus->users()->find($identifier);
    }

    /**
     * Retrieve a user by their token.
     */
    public function retrieveByToken($identifier, $token)
    {
        $this->cerberus->getHttpClient()->withToken($token);

        return $this->cerberus->auth()->findByToken($token);
    }

    /**
     * Update the "remember me" token for the user.
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        $this->cerberus
            ->users()
            ->where($user->getAuthIdentifierName(), $user->getEmailForPasswordReset())
            ->first()
            ->update(['remember_token' => $token]);
    }

    /**
     * Retrieve a user by the given credentials.
     */
    public function retrieveByCredentials(array $credentials)
    {
        $credentials = array_filter(
            $credentials,
            fn ($key) => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($credentials)) {
            return null;
        }

        $query = $this->cerberus->users();

        foreach ($credentials as $key => $value) {
            if (is_array($value)) {
                $query->whereIn($key, $value);
            } elseif ($value instanceof Closure) {
                $value($query);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    /**
     * Validate a user against the given credentials.
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if (is_null($credentials['password'] ?? null)) {
            return false;
        }

        try {
            return $this->cerberus
                ->auth()
                ->user($user)
                ->checkPassword($credentials);
        } catch (Throwable $th) {
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
        return $this->cerberus
            ->auth()
            ->user($user)
            ->rehashPasswordIfRequired($credentials, $force);
    }

    /**
     * Get the Cerberus client instance.
     */
    public function getConnection(): Cerberus
    {
        return $this->cerberus;
    }
}
