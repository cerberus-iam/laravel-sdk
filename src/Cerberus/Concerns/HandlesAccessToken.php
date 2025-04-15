<?php

namespace Cerberus\Concerns;

use Cerberus\Contracts\TokenStorage;
use Cerberus\Events\AccessTokenCreated;
use Cerberus\Events\RefreshTokenCreated;
use Cerberus\Resources\Token;
use Cerberus\TokenParser;
use Illuminate\Support\Facades\Event;
use RuntimeException;

trait HandlesAccessToken
{
    /**
     * The token storage implementation.
     */
    protected ?TokenStorage $storage = null;

    /**
     * The override for the client ID.
     */
    protected ?string $clientIdOverride = null;

    /**
     * The override for the client secret.
     */
    protected ?string $clientSecretOverride = null;

    /**
     * Configure the access token on the HTTP client.
     */
    public function configureAccessToken(): self
    {
        if (! $this->http->hasHeader('Authorization')) {
            $this->http->withToken($this->getAccessToken()['access_token']);
        }

        return $this;
    }

    /**
     * Get the access token from storage or request a new one.
     *
     * @return array{access_token: string, expires_in: int, refresh_token?: string}
     */
    public function getAccessToken(): array
    {
        $cached = $this->getTokenStorage()->get();

        if (is_array($cached) && isset($cached['access_token'], $cached['expires_in'])) {
            if ($this->isTokenExpired($cached)) {
                return $this->refreshAccessToken($cached['refresh_token'] ?? null);
            }

            return $cached;
        }

        return $this->requestNewAccessToken();
    }

    /**
     * Return a parsed Token resource from the current access token.
     */
    public function parsedToken(): Token
    {
        return TokenParser::parseAccessToken($this->getAccessToken()['access_token']);
    }

    /**
     * Get the token storage implementation.
     *
     * @throws RuntimeException
     */
    public function getTokenStorage(): TokenStorage
    {
        if (! isset($this->storage)) {
            $this->initialiseStorage();
        }

        return $this->storage;
    }

    /**
     * Inject a token storage implementation.
     */
    public function setTokenStorage(TokenStorage $storage): void
    {
        $this->storage = $storage;
    }

    /**
     * Initialise the token storage implementation.
     *
     * @throws RuntimeException
     */
    protected function initialiseStorage(): void
    {
        $this->storage = app(TokenStorage::class);
    }

    /**
     * Request a new access token using client credentials.
     */
    protected function requestNewAccessToken(): array
    {
        $grantType = defined('static::GRANT_TYPE') ? static::GRANT_TYPE : 'client_credentials';

        $response = $this->http->post('/oauth/token', [
            'grant_type' => $grantType,
            'client_id' => $this->clientIdOverride ?? config('services.cerberus.key'),
            'client_secret' => $this->clientSecretOverride ?? config('services.cerberus.secret'),
            'scope' => '*',
        ]);

        return $this->storeAccessTokenResponse($response->json());
    }

    /**
     * Refresh an access token using a refresh token.
     */
    protected function refreshAccessToken(?string $refreshToken = null): array
    {
        if (! $refreshToken) {
            return $this->requestNewAccessToken();
        }

        $response = $this->http->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientIdOverride ?? config('services.cerberus.key'),
            'client_secret' => $this->clientSecretOverride ?? config('services.cerberus.secret'),
            'scope' => '*',
        ]);

        return $this->storeAccessTokenResponse($response->json(), isRefresh: true);
    }

    /**
     * Store token data and fire appropriate events.
     */
    protected function storeAccessTokenResponse(array $data, bool $isRefresh = false): array
    {
        if (! isset($data['access_token'], $data['expires_in'])) {
            throw new RuntimeException('Invalid access token response from Cerberus.');
        }

        $this->getTokenStorage()->put($data, $data['expires_in']);

        $token = TokenParser::parseAccessToken($data['access_token']);

        Event::dispatch(new AccessTokenCreated(
            tokenId: $token->getTokenId(),
            userId: $token->getUserId(),
            clientId: $token->getClientId()
        ));

        if ($isRefresh && isset($data['refresh_token'])) {
            $refresh = TokenParser::parseRefreshToken($data['refresh_token'], $token->getTokenId());

            Event::dispatch(new RefreshTokenCreated(
                refreshTokenId: $refresh->getTokenId(),
                accessTokenId: $token->getTokenId()
            ));
        }

        return $data;
    }

    /**
     * Determine if the token is expired.
     */
    protected function isTokenExpired(array $token): bool
    {
        return TokenParser::parseAccessToken($token['access_token'])->isExpired();
    }
}
