<?php

namespace Cerberus;

use Cerberus\Contracts\TokenStorage;
use Illuminate\Contracts\Session\Session;

class SessionTokenStorage implements TokenStorage
{
    /**
     * Create new SessionTokenStorage instance.
     *
     * @return void
     */
    public function __construct(protected string $key, protected Session $session)
    {
        //
    }

    public function get(): ?array
    {
        return $this->session->get($this->key);
    }

    public function put(array $data, int $ttlSeconds): void
    {
        $data['expires_at'] = now()->addSeconds($ttlSeconds);
        $this->session->put($this->key, $data);
    }

    public function forget(): void
    {
        $this->session->forget($this->key);
    }
}
