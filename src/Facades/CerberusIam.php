<?php

declare(strict_types=1);

namespace CerberusIAM\Facades;

use CerberusIAM\Contracts\IamClient;
use Illuminate\Support\Facades\Facade;

/**
 * Cerberus IAM Facade
 *
 * This facade provides a convenient static interface to the Cerberus IAM client,
 * allowing easy access to IAM operations throughout the application.
 *
 *
 * @method static string sessionCookieName()
 * @method static string buildAuthorizationUrl(string $state, string $codeVerifier, ?string $returnTo = null)
 * @method static string generateCodeVerifier()
 * @method static array exchangeAuthorizationCode(string $code, ?string $codeVerifier = null)
 * @method static array refreshAccessToken(string $refreshToken)
 * @method static array|null getUserInfo(string $accessToken)
 * @method static array|null getCurrentUserFromSession(string $sessionToken)
 * @method static void logoutSession(string $sessionToken)
 * @method static void revokeTokens(?string $accessToken, ?string $refreshToken)
 * @method static string url(string $path)
 *
 * @see \CerberusIAM\Contracts\IamClient
 */
class CerberusIam extends Facade
{
    /**
     * Get the facade accessor.
     *
     * This method returns the class name of the service that the facade provides access to.
     *
     * @return string The class name of the underlying service (IamClient).
     */
    protected static function getFacadeAccessor(): string
    {
        // Return the IamClient class as the facade accessor
        return IamClient::class;
    }
}
