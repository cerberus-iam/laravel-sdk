<?php

namespace Cerberus\Storage;

use Cerberus\Contracts\TokenStorage;
use Illuminate\Contracts\Session\Session;

class SessionTokenStorage implements TokenStorage
{
    /**
     * Create new SessionTokenStorage instance.
     *
     * @return void
     */
    public function __construct(
        protected string $key,
        protected Session $session
    ) {
        //
    }

    /**
     * Retrieve the current token from cache.
     */
    public function get(): ?array
    {
        return $this->session->get($this->key);
    }

    /**
     * Store the token payload in cache.
     */
    public function put(array $data, int $ttlSeconds): void
    {
        $data['expires_at'] = now()->addSeconds($ttlSeconds);
        $this->session->put($this->key, $data);
    }

    /**
     * Forget the token from cache.
     */
    public function forget(): void
    {
        $this->session->forget($this->key);
    }
}
