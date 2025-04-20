<?php

namespace Cerberus\Guards;

use Cerberus\Cerberus;
use Illuminate\Auth\SessionGuard as IlluminateSessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Custom session guard for Cerberus IAM.
 */
class SessionGuard extends IlluminateSessionGuard
{
    /**
     * Get the currently authenticated user.
     */
    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $user = parent::user();

        if ($user) {
            $cerberus = $this->cerberus();

            try {
                $cerberus->actingAs($user);

                // Get access token from Cerberus storage
                // This will automatically refresh the token if it's expired
                $cerberus->getAccessToken();

                app()->instance(Cerberus::class, $cerberus);
            } catch (Throwable $e) {
                Log::warning('[Cerberus\SessionGuard] Failed to get or refresh token.', [
                    'user_id' => $user->getAuthIdentifier(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return $user;
    }

    /**
     * Attempt to authenticate the user using Cerberus IAM.
     */
    public function attempt(array $credentials = [], $remember = false): bool
    {
        $cerberus = $this->cerberus();

        try {
            // Request token via password grant
            $tokenData = $cerberus->requestAccessTokenWithPassword($credentials);

            // Token is automatically stored by requestAccessTokenWithPassword

            // Retrieve authenticated user
            $user = $this->provider->retrieveByToken(null, $tokenData['access_token']);

            if (! $user) {
                return false;
            }

            $this->login($user, $remember);

            app()->instance(Cerberus::class, $cerberus->actingAs($user));

            return true;
        } catch (Throwable $e) {
            Log::warning('[Cerberus\SessionGuard] Login attempt failed.', [
                'email' => $credentials['email'] ?? null,
                'exception' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Log the user out and forget Cerberus tokens.
     */
    public function logout(): void
    {
        try {
            // Use Cerberus's built-in token purging
            $this->cerberus()->purgeToken(true);
        } catch (Throwable $e) {
            Log::warning('[Cerberus\SessionGuard] Failed to purge token during logout', [
                'exception' => $e->getMessage(),
            ]);
        }

        parent::logout();
    }

    /**
     * Resolve the Cerberus client from the container.
     */
    protected function cerberus(): Cerberus
    {
        return app(Cerberus::class);
    }
}
