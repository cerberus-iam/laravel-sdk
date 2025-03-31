<?php

namespace Cerberus;

use Cerberus\Contracts\TokenStorage;
use Illuminate\Support\Facades\Cache;

class CacheTokenStorage implements TokenStorage
{
    /**
     * Cache key for the access token.
     */
    protected string $cacheKey;

    /**
     * Constructor.
     */
    public function __construct(string $cacheKey = Cerberus::CACHE_KEY_TOKEN)
    {
        $this->cacheKey = $cacheKey;
    }

    /**
     * Retrieve the current token from cache.
     */
    public function get(): ?array
    {
        return Cache::get($this->cacheKey);
    }

    /**
     * Store the token payload in cache.
     */
    public function put(array $data, int $ttlSeconds): void
    {
        Cache::put($this->cacheKey, $data, now()->addSeconds($ttlSeconds));
    }

    /**
     * Forget the token from cache.
     */
    public function forget(): void
    {
        Cache::forget($this->cacheKey);
    }
}
