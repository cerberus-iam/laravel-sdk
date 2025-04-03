<?php

namespace Cerberus;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Facades\Cache;

class CerberusUserProvider implements UserProvider
{
    /**
     * In-memory cache of users for the request lifecycle.
     *
     * @var array<string, \Illuminate\Contracts\Auth\Authenticatable>
     */
    protected array $cachedUsers = [];

    /**
     * Cache lifetime for persistent user cache (in seconds).
     */
    protected int $cacheTtl = 300; // 5 minutes

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
        if (isset($this->cachedUsers[$identifier])) {
            return $this->cachedUsers[$identifier];
        }

        $cacheKey = $this->getCacheKey($identifier);

        $user = Cache::remember($cacheKey, $this->cacheTtl, fn () => $this->findUserById($identifier));

        return $this->cachedUsers[$identifier] = $user;
    }

    /**
     * Helper method to isolate user lookup (avoids closure serialization issues).
     */
    protected function findUserById($identifier)
    {
        return $this->cerberus->users()->find($identifier);
    }

    /**
     * Retrieve a user by their token.
     */
    public function retrieveByToken($identifier, $token)
    {
        // In-memory cache
        if (isset($this->cachedUsers[$token])) {
            return $this->cachedUsers[$token];
        }

        $this->cerberus->getHttpClient()->withToken($token);

        $user = $this->cerberus->auth()->findByToken($token);

        return $this->cachedUsers[$token] = $user;
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

        // Also update cached user if present
        if (isset($this->cachedUsers[$user->getAuthIdentifier()])) {
            $this->cachedUsers[$user->getAuthIdentifier()]->remember_token = $token;
        }

        // Optionally clear persistent cache
        Cache::forget($this->getCacheKey($user->getAuthIdentifier()));
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

        return $this->cerberus
            ->auth()
            ->user($user)
            ->checkPassword($credentials);
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

    /**
     * Generate the cache key for storing a user by identifier.
     */
    protected function getCacheKey(string $identifier): string
    {
        return 'cerberus:user:'.$identifier;
    }
}
