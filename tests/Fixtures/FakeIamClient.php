<?php

declare(strict_types=1);

namespace CerberusIAM\Tests\Fixtures;

use CerberusIAM\Contracts\IamClient;

/**
 * Fake IAM Client
 *
 * This class is a fake implementation of the IamClient interface for testing purposes,
 * allowing tests to mock API responses without making real HTTP requests.
 */
class FakeIamClient implements IamClient
{
    /**
     * Mock responses for userinfo requests.
     */
    public array $userinfoResponses = [];

    /**
     * Mock session profiles.
     */
    public array $sessionProfiles = [];

    /**
     * Mock token exchange responses.
     */
    public array $tokenResponses = [];

    /**
     * Mock token refresh responses.
     */
    public array $refreshResponses = [];

    /**
     * Recorded token revocations.
     */
    public array $revocations = [];

    /**
     * Recorded logout calls.
     */
    public array $logoutCalls = [];

    /**
     * Mock responses for getUserById requests.
     */
    public array $userByIdResponses = [];

    /**
     * Create a new fake IAM client instance.
     *
     * @param  string|null  $sessionCookie  The session cookie name.
     * @param  string  $baseUrl  The base URL for the fake API.
     */
    public function __construct(
        public ?string $sessionCookie = 'cerb_sid',
        public string $baseUrl = 'https://cerberus.test'
    ) {}

    /**
     * Get the session cookie name.
     *
     * @return string|null The session cookie name.
     */
    public function sessionCookieName(): ?string
    {
        return $this->sessionCookie;
    }

    /**
     * Build the authorization URL.
     *
     * @param  string  $state  The OAuth state.
     * @param  string  $codeVerifier  The code verifier.
     * @param  string|null  $returnTo  The return URL.
     * @return string The authorization URL.
     */
    public function buildAuthorizationUrl(string $state, string $codeVerifier, ?string $returnTo = null): string
    {
        // Build query parameters
        $query = http_build_query([
            'state' => $state,
            'code_verifier' => $codeVerifier,
            'return_to' => $returnTo,
        ]);

        // Return the fake authorization URL
        return "{$this->baseUrl}/oauth2/authorize?{$query}";
    }

    /**
     * Generate a code verifier.
     *
     * @return string The code verifier.
     */
    public function generateCodeVerifier(): string
    {
        return 'fake-verifier';
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @param  string  $code  The authorization code.
     * @param  string|null  $codeVerifier  The code verifier.
     * @return array<string, mixed> The token response.
     */
    public function exchangeAuthorizationCode(string $code, ?string $codeVerifier = null): array
    {
        // Return mock response or default
        return $this->tokenResponses[$code] ?? [
            'access_token' => "token-{$code}",
            'refresh_token' => "refresh-{$code}",
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ];
    }

    /**
     * Refresh an access token.
     *
     * @param  string  $refreshToken  The refresh token.
     * @return array<string, mixed> The token response.
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        // Return mock response or default
        return $this->refreshResponses[$refreshToken] ?? [
            'access_token' => "refreshed-{$refreshToken}",
            'refresh_token' => $refreshToken,
            'expires_in' => 3600,
        ];
    }

    /**
     * Get user information.
     *
     * @param  string  $accessToken  The access token.
     * @return array<string, mixed>|null The user information.
     */
    public function getUserInfo(string $accessToken): ?array
    {
        // Return mock response or default
        return $this->userinfoResponses[$accessToken] ?? [
            'id' => 'usr_123',
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
        ];
    }

    /**
     * Get user information by ID.
     *
     * @param  string  $userId  The user ID.
     * @return array<string, mixed>|null The user information.
     */
    public function getUserById(string $userId): ?array
    {
        // Return mock response or default
        return $this->userByIdResponses[$userId] ?? [
            'id' => $userId,
            'email' => 'user@example.com',
            'name' => 'Test User',
            'firstName' => 'Test',
            'lastName' => 'User',
        ];
    }

    /**
     * Get current user from session.
     *
     * @param  string  $sessionToken  The session token.
     * @return array<string, mixed>|null The user information.
     */
    public function getCurrentUserFromSession(string $sessionToken): ?array
    {
        // Return mock session profile
        return $this->sessionProfiles[$sessionToken] ?? null;
    }

    /**
     * Logout a session.
     *
     * @param  string  $sessionToken  The session token.
     */
    public function logoutSession(string $sessionToken): void
    {
        // Record the logout call
        $this->logoutCalls[] = $sessionToken;
    }

    /**
     * Revoke tokens.
     *
     * @param  string|null  $accessToken  The access token.
     * @param  string|null  $refreshToken  The refresh token.
     */
    public function revokeTokens(?string $accessToken, ?string $refreshToken): void
    {
        // Record the revocation
        $this->revocations[] = [$accessToken, $refreshToken];
    }

    /**
     * Build a URL.
     *
     * @param  string  $path  The path.
     * @return string The URL.
     */
    public function url(string $path): string
    {
        return $this->baseUrl.$path;
    }
}
