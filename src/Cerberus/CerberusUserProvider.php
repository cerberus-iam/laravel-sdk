<?php

namespace Cerberus;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

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
     * {@inheritdoc}
     */
    public function retrieveById($identifier)
    {
        return $this->cerberus->users()->find($identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByToken($identifier, $token)
    {
        $this->cerberus->getHttpClient()->withToken($token);

        return $this->cerberus->auth()->findByToken($token);
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function retrieveByCredentials(array $credentials)
    {
        $credentials = array_filter(
            $credentials,
            fn ($key) => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($credentials)) {
            return;
        }

        // First we will add each credential element to the query as a where clause.
        // Then we can execute the query and, if we found a user, return it in a
        // Cerberus User "model" that will be utilized by the Guard instances.
        $query = $this->cerberus->users();

        foreach ($credentials as $key => $value) {
            if (is_array($value) || $value instanceof Arrayable) {
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
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if (is_null($credentials['password'])) {
            return false;
        }

        return $this->cerberus
            ->auth()
            ->user($user)
            ->checkPassword($credentials);
    }

    /**
     * {@inheritdoc}
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
