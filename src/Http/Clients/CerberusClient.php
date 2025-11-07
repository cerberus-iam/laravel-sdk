<?php

declare(strict_types=1);

namespace CerberusIAM\Http\Clients;

use CerberusIAM\Contracts\IamClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Cerberus IAM HTTP Client
 *
 * Implements the IamClient contract by delegating HTTP requests to Laravel's HTTP client.
 */
class CerberusClient implements IamClient
{
    protected string $baseUrl;

    protected HttpFactory $http;

    /**
     * Create a new Cerberus IAM HTTP client instance.
     *
     * @param  string  $baseUrl  The base URL for the Cerberus IAM API
     * @param  string|null  $sessionCookie  The name of the session cookie for session-based authentication
     * @param  string|null  $organisationSlug  The organisation slug for admin operations
     * @param  array<string, mixed>  $oauthConfig  OAuth2 configuration including client_id, client_secret, redirect_uri, scopes
     * @param  array<string, mixed>  $httpConfig  HTTP client configuration including timeout and retry settings
     * @param  HttpFactory|null  $http  HTTP client factory (optional, defaults to new instance)
     */
    public function __construct(
        string $baseUrl,
        protected ?string $sessionCookie,
        protected ?string $organisationSlug,
        protected array $oauthConfig,
        protected array $httpConfig = [],
        ?HttpFactory $http = null
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->http = $http ?? new HttpFactory;
    }

    /**
     * Get the name of the session cookie used for authentication.
     *
     * @return string|null The session cookie name or null if not configured
     */
    public function sessionCookieName(): ?string
    {
        return $this->sessionCookie;
    }

    /**
     * Build the OAuth2 authorization URL for initiating the login flow.
     *
     * Constructs the authorization URL with PKCE code challenge, state parameter,
     * and optional return URL for post-login redirection.
     *
     * @param  string  $state  The OAuth2 state parameter for CSRF protection
     * @param  string  $codeVerifier  The PKCE code verifier for secure code exchange
     * @param  string|null  $returnTo  Optional URL to redirect to after successful authentication
     * @return string The complete authorization URL
     */
    public function buildAuthorizationUrl(string $state, string $codeVerifier, ?string $returnTo = null): string
    {
        $query = [
            'response_type' => 'code',
            'client_id' => $this->oauthConfig['client_id'],
            'redirect_uri' => $this->oauthConfig['redirect_uri'],
            'scope' => implode(' ', Arr::wrap($this->oauthConfig['scopes'] ?? [])),
            'state' => $state,
            'code_challenge' => $this->generateCodeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ];

        if ($returnTo) {
            $query['return_to'] = $returnTo;
        }

        unset($body['client_secret']);

        return $this->url('/oauth2/authorize').'?'.http_build_query($query);
    }

    /**
     * Generate a cryptographically secure random string for PKCE code verifier.
     *
     * @return string A 64-character random string suitable for PKCE
     */
    public function generateCodeVerifier(): string
    {
        return Str::random(64);
    }

    /**
     * Exchange an authorization code for access and refresh tokens.
     *
     * Performs the OAuth2 token exchange using the authorization code
     * received from the authorization callback, optionally validating
     * with PKCE code verifier.
     *
     * @param  string  $code  The authorization code from the OAuth2 callback
     * @param  string|null  $codeVerifier  The PKCE code verifier (optional but recommended)
     * @return array<string, mixed> The token response containing access_token, refresh_token, etc.
     *
     * @throws RuntimeException When the token exchange fails
     */
    public function exchangeAuthorizationCode(string $code, ?string $codeVerifier = null): array
    {
        $payload = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->oauthConfig['redirect_uri'],
        ];

        if ($codeVerifier) {
            $payload['code_verifier'] = $codeVerifier;
        }

        return $this->tokenRequest($payload);
    }

    /**
     * Refresh an access token using a refresh token.
     *
     * Exchanges a valid refresh token for a new access token and optionally
     * a new refresh token, maintaining the user's authenticated session.
     *
     * @param  string  $refreshToken  The refresh token to exchange
     * @return array<string, mixed> The token response with new access_token and refresh_token
     *
     * @throws RuntimeException When the token refresh fails
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * Retrieve user information using an access token.
     *
     * Fetches the authenticated user's profile information from the
     * OAuth2 userinfo endpoint using the provided access token.
     *
     * @param  string  $accessToken  The access token for authentication
     * @return array<string, mixed>|null The user information or null if request fails
     */
    public function getUserInfo(string $accessToken): ?array
    {
        $response = $this->http()
            ->withToken($accessToken)
            ->get('/oauth2/userinfo');

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Retrieve the current user's profile using a session token.
     *
     * Fetches user profile information from the session-based authentication
     * endpoint using the session cookie for authentication.
     *
     * @param  string  $sessionToken  The session token from the user's session
     * @return array<string, mixed>|null The user profile data or null if not authenticated
     */
    public function getCurrentUserFromSession(string $sessionToken): ?array
    {
        if (! $this->sessionCookie) {
            return null;
        }

        $response = $this->http()
            ->withHeaders([
                'Cookie' => sprintf('%s=%s', $this->sessionCookie, $sessionToken),
            ])
            ->get('/v1/me/profile');

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();

        return $data['data'] ?? $data;
    }

    /**
     * Log out the user by invalidating their session.
     *
     * Sends a logout request to invalidate the user's session token,
     * effectively ending their authenticated session.
     *
     * @param  string  $sessionToken  The session token to invalidate
     */
    public function logoutSession(string $sessionToken): void
    {
        if (! $this->sessionCookie) {
            return;
        }

        $this->http()
            ->withHeaders([
                'Cookie' => sprintf('%s=%s', $this->sessionCookie, $sessionToken),
            ])
            ->post('/v1/auth/logout');
    }

    /**
     * Revoke access and/or refresh tokens.
     *
     * Invalidates the specified tokens by sending them to the OAuth2
     * revocation endpoint, preventing further use of these tokens.
     *
     * @param  string|null  $accessToken  The access token to revoke
     * @param  string|null  $refreshToken  The refresh token to revoke
     */
    public function revokeTokens(?string $accessToken, ?string $refreshToken): void
    {
        $tokens = array_filter([$accessToken, $refreshToken]);

        foreach ($tokens as $token) {
            $payload = ['token' => $token];

            if (! empty($this->oauthConfig['client_secret'])) {
                $payload['client_id'] = $this->oauthConfig['client_id'];
                $payload['client_secret'] = $this->oauthConfig['client_secret'];
            }

            $this->http()
                ->asForm()
                ->post('/oauth2/revoke', $payload);
        }
    }

    /**
     * Retrieve a user by their ID using client credentials.
     *
     * Fetches user information from the admin API using client credentials
     * authentication. Requires organisation slug to be configured.
     *
     * @param  string  $id  The user ID to look up
     * @return array<string, mixed>|null The user data or null if not found or access denied
     */
    public function getUserById(string $id): ?array
    {
        if (empty($this->organisationSlug)) {
            return null;
        }

        $accessToken = $this->getClientCredentialsAccessToken();

        if (! $accessToken) {
            return null;
        }

        $response = $this->http()
            ->withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'X-Org-Domain' => $this->organisationSlug,
            ])
            ->get("/v1/admin/users/{$id}");

        return $response->successful() ? $response->json() : null;
    }

    /**
     * Perform a token request to the OAuth2 token endpoint.
     *
     * Handles various OAuth2 token flows (authorization code, refresh token,
     * client credentials) by sending the appropriate payload to the token endpoint.
     *
     * @param  array<string, mixed>  $body  The request body parameters
     * @return array<string, mixed> The token response
     *
     * @throws RuntimeException When the token request fails
     */
    protected function tokenRequest(array $body): array
    {
        $headers = [];
        $clientId = $this->oauthConfig['client_id'] ?? null;
        $clientSecret = $this->oauthConfig['client_secret'] ?? null;

        if ($clientId) {
            $body['client_id'] = $clientId;
        }

        if ($clientSecret) {
            // Confidential client: authenticate via HTTP Basic instead of sending the secret in the form body
            $headers['Authorization'] = 'Basic '.base64_encode(sprintf('%s:%s', $clientId, $clientSecret));
        }

        $response = $this->http()
            ->asForm()
            ->withHeaders($headers)
            ->post('/oauth2/token', $body);

        if (! $response->successful()) {
            throw new RuntimeException('Cerberus token request failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Build a full URL for a given path.
     */
    public function url(string $path): string
    {
        return $this->baseUrl.$path;
    }

    /**
     * Prepare a base HTTP request with common configuration.
     */
    protected function http(): PendingRequest
    {
        $request = $this->http->baseUrl($this->baseUrl)->acceptJson();

        if (isset($this->httpConfig['timeout'])) {
            $request = $request->timeout((int) $this->httpConfig['timeout']);
        }

        if (Arr::get($this->httpConfig, 'retry.enabled')) {
            $request = $request->retry(
                (int) Arr::get($this->httpConfig, 'retry.max_attempts', 2),
                (int) Arr::get($this->httpConfig, 'retry.delay', 100)
            );
        }

        return $request;
    }

    /**
     * Generate a PKCE code challenge from a code verifier.
     */
    protected function generateCodeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * Cached client credentials token payload.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $clientCredentialsToken = null;

    /**
     * Get an access token using client credentials flow.
     *
     * Obtains an access token for machine-to-machine authentication using
     * the configured client credentials. Tokens are cached until expiry.
     *
     * @return string|null The access token or null if client credentials not configured
     */
    protected function getClientCredentialsAccessToken(): ?string
    {
        if (empty($this->oauthConfig['client_secret'])) {
            return null;
        }

        // Check if we have a cached token that hasn't expired
        if ($this->clientCredentialsToken && isset($this->clientCredentialsToken['expires_at'])) {
            if (Carbon::parse($this->clientCredentialsToken['expires_at'])->isFuture()) {
                return $this->clientCredentialsToken['access_token'] ?? null;
            }
        }

        $payload = [
            'grant_type' => 'client_credentials',
        ];

        $scopes = Arr::wrap($this->oauthConfig['scopes'] ?? []);
        if (! empty($scopes)) {
            $payload['scope'] = implode(' ', $scopes);
        }

        try {
            $tokenResponse = $this->tokenRequest($payload);
        } catch (RuntimeException) {
            return null;
        }

        $this->clientCredentialsToken = $this->normalizeTokenPayload($tokenResponse);

        return $this->clientCredentialsToken['access_token'] ?? null;
    }

    /**
     * Normalise token payloads by adding an ISO8601 expiry timestamp.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeTokenPayload(array $payload): array
    {
        if (isset($payload['expires_in'])) {
            $payload['expires_at'] = Carbon::now()->addSeconds((int) $payload['expires_in'])->toIso8601String();
        }

        return $payload;
    }
}
