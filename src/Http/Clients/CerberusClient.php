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
     * @param  array<string, mixed>  $oauthConfig
     * @param  array<string, mixed>  $httpConfig
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
        $this->http = $http ?? new HttpFactory();
    }

    public function sessionCookieName(): ?string
    {
        return $this->sessionCookie;
    }

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

        return $this->url('/oauth2/authorize').'?'.http_build_query($query);
    }

    public function generateCodeVerifier(): string
    {
        return Str::random(64);
    }

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

    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function getUserInfo(string $accessToken): ?array
    {
        $response = $this->http()
            ->withToken($accessToken)
            ->get('/oauth2/userinfo');

        return $response->successful() ? $response->json() : null;
    }

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

    protected function tokenRequest(array $body): array
    {
        if (! empty($this->oauthConfig['client_secret'])) {
            $body['client_id'] = $this->oauthConfig['client_id'];
            $body['client_secret'] = $this->oauthConfig['client_secret'];
        }

        $headers = [];

        if (! empty($this->oauthConfig['client_id'])) {
            $headers['Authorization'] = 'Basic '.base64_encode(sprintf(
                '%s:%s',
                $this->oauthConfig['client_id'],
                $this->oauthConfig['client_secret'] ?? ''
            ));
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

    public function url(string $path): string
    {
        return $this->baseUrl.$path;
    }

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
