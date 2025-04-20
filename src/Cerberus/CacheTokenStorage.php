<?php

namespace Cerberus;

use Cerberus\Contracts\TokenStorage;
use Illuminate\Support\Facades\Cache;

class CacheTokenStorage implements TokenStorage
{
    /**
     * Create new CacheTokenStorage instance.
     *
     * @return void
     */
    public function __construct(protected string $key = Cerberus::TOKEN_STORAGE_KEY)
    {
        //
    }

    /**
     * Retrieve the current token from cache.
     */
    public function get(): ?array
    {
        return Cache::get($this->key);
    }

    /**
     * Store the token payload in cache.
     */
    public function put(array $data, int $ttlSeconds): void
    {
        Cache::put($this->key, $data, now()->addSeconds($ttlSeconds));
    }

    /**
     * Forget the token from cache.
     */
    public function forget(): void
    {
        Cache::forget($this->key);
    }
}
