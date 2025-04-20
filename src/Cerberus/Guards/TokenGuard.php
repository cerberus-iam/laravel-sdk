<?php

namespace Cerberus\Guards;

use Cerberus\Exceptions\AuthenticationException;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Traits\Macroable;

class TokenGuard implements StatefulGuard
{
    use GuardHelpers, Macroable;

    /**
     * The current HTTP request instance.
     */
    protected ?Request $request;

    /**
     * Create a new token guard instance.
     *
     * @return void
     */
    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request = $request;
    }

    /**
     * Get the currently authenticated user for the request.
     *
     * If the token is valid, the user is retrieved via the UserProvider.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     *
     * @throws AuthenticationException If token is present but invalid or expired.
     */
    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->getTokenFromRequest();

        if (! $token) {
            return null;
        }

        $user = $this->provider->retrieveByToken(null, $token);

        if (! $user) {
            throw new AuthenticationException('Invalid or expired access token.');
        }

        return $this->user = $user;
    }

    /**
     * Validate user credentials (typically for login attempts).
     *
     * Note: This method does not persist the user instance into the guard.
     *
     * @param  array<string, mixed>  $credentials
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        return (bool) $this->provider->retrieveByCredentials($credentials);
    }

    /**
     * Attempt to authenticate a user using the given credentials.
     *
     * @param  bool  $remember
     * @return bool
     */
    public function attempt(array $credentials = [], $remember = false)
    {
        //
    }

    /**
     * Log a user into the application without sessions or cookies.
     *
     * @return bool
     */
    public function once(array $credentials = [])
    {
        //
    }

    /**
     * Log a user into the application.
     *
     * @param  bool  $remember
     * @return void
     */
    public function login(Authenticatable $user, $remember = false)
    {
        //
    }

    /**
     * Log the given user ID into the application.
     *
     * @param  mixed  $id
     * @param  bool  $remember
     * @return \Illuminate\Contracts\Auth\Authenticatable|false
     */
    public function loginUsingId($id, $remember = false)
    {
        //
    }

    /**
     * Log the given user ID into the application without sessions or cookies.
     *
     * @param  mixed  $id
     * @return \Illuminate\Contracts\Auth\Authenticatable|false
     */
    public function onceUsingId($id)
    {
        //
    }

    /**
     * Determine if the user was authenticated via "remember me" cookie.
     *
     * @return bool
     */
    public function viaRemember()
    {
        //
    }

    /**
     * Log the user out of the application.
     *
     * @return void
     */
    public function logout()
    {
        //
    }

    /**
     * Set the current HTTP request instance on the guard.
     *
     * @return self
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Extract the bearer token from the Authorization header.
     *
     * @return string|null The bearer token, or null if not present.
     */
    protected function getTokenFromRequest(): ?string
    {
        return $this->request->bearerToken();
    }
}
