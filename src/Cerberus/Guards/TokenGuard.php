<?php

namespace Cerberus\Guards;

use Cerberus\Exceptions\AuthenticationException;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Traits\Macroable;

class TokenGuard implements Guard
{
    use GuardHelpers, Macroable;

    /**
     * The HTTP request instance.
     */
    protected ?Request $request;

    /**
     * Create a new token guard instance.
     */
    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request = $request;
    }

    /**
     * Get the currently authenticated user.
     *
     * @throws \Cerberus\Exceptions\AuthenticationException
     */
    public function user(): ?Authenticatable
    {
        if (! is_null($this->user)) {
            return $this->user;
        }

        $token = $this->request->bearerToken();

        if (! $token) {
            return null;
        }

        $user = $this->provider->retrieveByToken(null, $token);

        if (! $user) {
            throw new AuthenticationException('Invalid or expired access token.');
        }

        $this->setUser($user);

        return $this->user;
    }

    /**
     * Validate a user's credentials.
     */
    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user) {
            $this->setUser($user);

            return true;
        }

        return false;
    }

    /**
     * Set the current request instance.
     */
    public function setRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }
}
