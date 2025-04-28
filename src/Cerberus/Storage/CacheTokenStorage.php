<?php

namespace Cerberus\Storage;

use Cerberus\Contracts\TokenStorage;
use Illuminate\Contracts\Cache\Store as Cache;

class CacheTokenStorage implements TokenStorage
{
    /**
     * Create new CacheTokenStorage instance.
     *
     * @return void
     */
    public function __construct(
        protected string $key,
        protected Cache $cache
    ) {
        //
    }

    /**
     * Retrieve the current token from cache.
     */
    public function get(): ?array
    {
        return $this->cache->get($this->key);
    }

    /**
     * Store the token payload in cache.
     */
    public function put(array $data, int $ttlSeconds): void
    {
        $this->cache->put($this->key, $data, now()->addSeconds($ttlSeconds));
    }

    /**
     * Forget the token from cache.
     */
    public function forget(): void
    {
        $this->cache->forget($this->key);
    }
}
