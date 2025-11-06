<?php

declare(strict_types=1);

namespace CerberusIAM\Contracts;

/**
 * IAM Client Contract
 *
 * This interface defines the contract for IAM client implementations,
 * providing methods to interact with the Cerberus IAM API for authentication,
 * authorization, and user management.
 */
interface IamClient
{
    /**
     * Get the name of the session cookie.
     *
     * This method returns the name of the cookie used to store the session token.
     *
     * @return string|null The session cookie name, or null if not configured.
     */
    public function sessionCookieName(): ?string;

    /**
     * Build the authorization URL for OAuth flow.
     *
     * This method constructs the URL for the OAuth authorization endpoint,
     * including state, code verifier, and optional return URL parameters.
     *
     * @param  string  $state  The OAuth state parameter for CSRF protection.
     * @param  string  $codeVerifier  The PKCE code verifier for security.
     * @param  string|null  $returnTo  Optional URL to return to after authorization.
     * @return string The complete authorization URL.
     */
    public function buildAuthorizationUrl(string $state, string $codeVerifier, ?string $returnTo = null): string;

    /**
     * Generate a code verifier for PKCE.
     *
     * This method generates a cryptographically secure random string
     * to be used as the code verifier in the PKCE OAuth flow.
     *
     * @return string The generated code verifier.
     */
    public function generateCodeVerifier(): string;

    /**
     * Exchange authorization code for tokens.
     *
     * This method exchanges the authorization code received from the OAuth callback
     * for access and refresh tokens.
     *
     * @param  string  $code  The authorization code from the OAuth callback.
     * @param  string|null  $codeVerifier  The PKCE code verifier, if used.
     * @return array<string, mixed> The token response containing access_token, refresh_token, etc.
     */
    public function exchangeAuthorizationCode(string $code, ?string $codeVerifier = null): array;

    /**
     * Refresh an access token.
     *
     * This method uses a refresh token to obtain a new access token
     * when the current one has expired.
     *
     * @param  string  $refreshToken  The refresh token to use for renewal.
     * @return array<string, mixed> The token response with new access token.
     */
    public function refreshAccessToken(string $refreshToken): array;

    /**
     * Get user information from access token.
     *
     * This method retrieves user profile information using a valid access token.
     *
     * @param  string  $accessToken  The access token for authentication.
     * @return array<string, mixed>|null The user information, or null if invalid.
     */
    public function getUserInfo(string $accessToken): ?array;

    /**
     * Get current user from session token.
     *
     * This method retrieves the current user's information using a session token.
     *
     * @param  string  $sessionToken  The session token for the user session.
     * @return array<string, mixed>|null The user information, or null if invalid.
     */
    public function getCurrentUserFromSession(string $sessionToken): ?array;

    /**
     * Logout a user session.
     *
     * This method invalidates the specified session token, effectively logging out the user.
     *
     * @param  string  $sessionToken  The session token to invalidate.
     */
    public function logoutSession(string $sessionToken): void;

    /**
     * Revoke access and refresh tokens.
     *
     * This method revokes the specified tokens, making them unusable for future requests.
     *
     * @param  string|null  $accessToken  The access token to revoke.
     * @param  string|null  $refreshToken  The refresh token to revoke.
     */
    public function revokeTokens(?string $accessToken, ?string $refreshToken): void;

    /**
     * Get user information by ID.
     *
     * This method retrieves detailed user information for a specific user by their unique identifier.
     *
     * @param  string  $userId  The unique identifier of the user.
     * @return array<string, mixed>|null The user information, or null if not found.
     */
    public function getUserById(string $userId): ?array;
}
