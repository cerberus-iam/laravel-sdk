<?php

declare(strict_types=1);

namespace CerberusIAM\Http\Clients;

use CerberusIAM\Contracts\IamClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

use function fetch;

/**
 * Cerberus IAM HTTP Client
 *
 * This class implements the IamClient interface, providing HTTP-based communication
 * with the Cerberus IAM service for authentication, authorization, and user management.
 */
class CerberusClient implements IamClient
{
    /**
     * The base URL of the Cerberus IAM service.
     */
    protected string $baseUrl;

    /**
     * Create a new Cerberus client instance.
     *
     * @param  string  $baseUrl  The base URL of the Cerberus IAM service.
     * @param  string|null  $sessionCookie  The name of the session cookie for session-based requests.
     * @param  array  $oauthConfig  The OAuth configuration including client_id, client_secret, redirect_uri, scopes.
     * @param  array  $httpConfig  Additional HTTP client configuration like timeout and retry settings.
     */
    public function __construct(
        string $baseUrl,
        protected ?string $sessionCookie,
        protected ?string $organisationSlug,
        protected array $oauthConfig,
        protected array $httpConfig = []
    ) {
        // Ensure the base URL does not end with a slash
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Get the name of the session cookie.
     *
     * @return string|null The session cookie name, or null if not configured.
     */
    public function sessionCookieName(): ?string
    {
        return $this->sessionCookie;
    }

    /**
     * Build the authorization URL for OAuth flow.
     *
     * This method constructs the OAuth authorization URL with all necessary parameters
     * including state, code challenge, and scopes.
     *
     * @param  string  $state  The OAuth state parameter for CSRF protection.
     * @param  string  $codeVerifier  The PKCE code verifier.
     * @param  string|null  $returnTo  Optional return URL after authorization.
     * @return string The complete authorization URL.
     */
    public function buildAuthorizationUrl(string $state, string $codeVerifier, ?string $returnTo = null): string
    {
        // Build the query parameters for the OAuth authorization request
        $query = [
            'response_type' => 'code',
            'client_id' => $this->oauthConfig['client_id'],
            'redirect_uri' => $this->oauthConfig['redirect_uri'],
            'scope' => implode(' ', Arr::wrap($this->oauthConfig['scopes'] ?? [])),
            'state' => $state,
            'code_challenge' => $this->generateCodeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ];

        // Add the return_to parameter if provided
        if ($returnTo) {
            $query['return_to'] = $returnTo;
        }

        // Construct the full authorization URL
        return $this->url('/oauth2/authorize').'?'.http_build_query($query);
    }

    /**
     * Generate a code verifier for PKCE.
     *
     * @return string The generated code verifier.
     */
    public function generateCodeVerifier(): string
    {
        return Str::random(64);
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @param  string  $code  The authorization code from the OAuth callback.
     * @param  string|null  $codeVerifier  The PKCE code verifier.
     * @return array<string, mixed> The token response.
     */
    public function exchangeAuthorizationCode(string $code, ?string $codeVerifier = null): array
    {
        // Prepare the payload for the token exchange request
        $payload = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->oauthConfig['redirect_uri'],
        ];

        // Include the code verifier if provided for PKCE
        if ($codeVerifier) {
            $payload['code_verifier'] = $codeVerifier;
        }

        // Make the token request and return the response
        return $this->tokenRequest($payload);
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function getUserInfo(string $accessToken): ?array
    {
        // Make a GET request to the OAuth2 userinfo endpoint
        $response = fetch($this->url('/oauth2/userinfo'), $this->applyDefaults([
            'method' => 'GET',
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$accessToken,
            ],
        ]));

        // Return the JSON response if the request was successful
        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    public function getCurrentUserFromSession(string $sessionToken): ?array
    {
        // Return null if session cookie is not configured
        if (! $this->sessionCookie) {
            return null;
        }

        // Make a GET request to the user profile endpoint using the session cookie
        $response = fetch($this->url('/v1/me/profile'), $this->applyDefaults([
            'method' => 'GET',
            'headers' => [
                'Accept' => 'application/json',
                'Cookie' => sprintf('%s=%s', $this->sessionCookie, $sessionToken),
            ],
        ]));

        // Parse the response and extract user data
        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['data'])) {
                return $data['data'];
            }

            return $data;
        }

        return null;
    }

    public function logoutSession(string $sessionToken): void
    {
        // Return early if session cookie is not configured
        if (! $this->sessionCookie) {
            return;
        }

        // Make a POST request to the logout endpoint to invalidate the session
        fetch($this->url('/v1/auth/logout'), $this->applyDefaults([
            'method' => 'POST',
            'headers' => [
                'Accept' => 'application/json',
                'Cookie' => sprintf('%s=%s', $this->sessionCookie, $sessionToken),
            ],
        ]));
    }

    public function revokeTokens(?string $accessToken, ?string $refreshToken): void
    {
        // Helper function to create payload for a token
        $payload = static function (?string $token) {
            return $token ? ['token' => $token] : null;
        };

        // Iterate over access and refresh tokens to revoke each one
        foreach ([$payload($accessToken), $payload($refreshToken)] as $body) {
            if (! $body) {
                continue;
            }

            // Prepare the request options for the revoke endpoint
            $options = [
                'method' => 'POST',
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => http_build_query($body),
            ];

            // Include client credentials in the request body (client_secret_post method)
            // Cerberus IAM requires this authentication method
            if (! empty($this->oauthConfig['client_secret'])) {
                $options['body'] .= '&'.http_build_query([
                    'client_id' => $this->oauthConfig['client_id'],
                    'client_secret' => $this->oauthConfig['client_secret'],
                ]);
            }

            // Make the revoke request
            fetch($this->url('/oauth2/revoke'), $this->applyDefaults($options));
        }
    }

    public function getUserById(string $id): ?array
    {
        if (empty($this->organisationSlug)) {
            return null;
        }

        $accessToken = $this->getClientCredentialsAccessToken();

        if (! $accessToken) {
            return null;
        }

        $response = fetch($this->url("/v1/admin/users/{$id}"), $this->applyDefaults([
            'method' => 'GET',
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$accessToken,
                'X-Org-Domain' => $this->organisationSlug,
            ],
        ]));

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    protected function tokenRequest(array $body): array
    {
        // Prepare headers for the token request
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        // Include client credentials in the body (client_secret_post method)
        // Cerberus IAM requires this authentication method
        if (! empty($this->oauthConfig['client_secret'])) {
            $body['client_id'] = $this->oauthConfig['client_id'];
            $body['client_secret'] = $this->oauthConfig['client_secret'];
        }

        // Make the POST request to the token endpoint
        $response = fetch($this->url('/oauth2/token'), $this->applyDefaults([
            'method' => 'POST',
            'headers' => $headers,
            'body' => http_build_query($body),
        ]));

        // Throw an exception if the request was not successful
        if (! $response->successful()) {
            throw new RuntimeException('Cerberus token request failed: '.$response->text());
        }

        // Return the JSON response
        return $response->json();
    }

    protected function generateCodeChallenge(string $verifier): string
    {
        // Generate the SHA256 hash of the verifier and base64url encode it
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public function url(string $path): string
    {
        // Concatenate the base URL with the provided path
        return $this->baseUrl.$path;
    }

    protected function applyDefaults(array $options): array
    {
        // Apply default timeout if not set and configured
        if (! isset($options['timeout']) && isset($this->httpConfig['timeout'])) {
            $options['timeout'] = (int) $this->httpConfig['timeout'];
        }

        // Apply default retry settings if not set and enabled
        if (! isset($options['retries']) && Arr::get($this->httpConfig, 'retry.enabled')) {
            $options['retries'] = (int) Arr::get($this->httpConfig, 'retry.max_attempts', 2);
        }

        // Return the options with defaults applied
        return $options;
    }

    /**
     * Cached client credentials token payload.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $clientCredentialsToken = null;

    protected function getClientCredentialsAccessToken(): ?string
    {
        if (empty($this->oauthConfig['client_secret'])) {
            return null;
        }

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
