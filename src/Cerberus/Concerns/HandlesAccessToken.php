<?php

namespace Cerberus\Concerns;

use Cerberus\Contracts\TokenStorage;
use Cerberus\Events\AccessTokenCreated;
use Cerberus\Events\AccessTokenPurged;
use Cerberus\Events\RefreshTokenCreated;
use Cerberus\Events\TokensPurged;
use Cerberus\Resources\Token;
use Cerberus\TokenParser;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Trait HandlesAccessToken
 *
 * Manages OAuth token retrieval, storage, and revocation.
 */
trait HandlesAccessToken
{
    /**
     * Storage for access tokens.
     */
    protected ?TokenStorage $storage = null;

    /**
     * Configure the Authorization header if missing.
     */
    public function configureAccessToken(): self
    {
        if (! $this->http->hasHeader('Authorization')) {
            $this->http->withToken($this->getAccessToken()['access_token']);
        }

        return $this;
    }

    /**
     * Use a specific bearer token for subsequent requests.
     */
    public function useToken(string $token): self
    {
        $this->http->withToken($token);

        try {
            $parsed = TokenParser::parseAccessToken($token);
            $expires = $parsed->getExpiresIn();

            $this->getTokenStorage()->put([
                'access_token' => $token,
                'expires_in' => $expires,
            ], $expires);
        } catch (Throwable) {
            // If parsing fails, skip storage
        }

        return $this;
    }

    /**
     * Get a valid access token, refreshing if expired.
     *
     * @return array{access_token: string, expires_in: int, refresh_token?: string}
     */
    public function getAccessToken(): array
    {
        $cached = $this->getTokenStorage()->get();

        if (is_array($cached) && isset($cached['access_token'], $cached['expires_in'])) {
            return TokenParser::parseAccessToken($cached['access_token'])->isExpired()
                ? $this->refreshAccessToken($cached['refresh_token'] ?? null)
                : $cached;
        }

        return $this->requestNewAccessToken();
    }

    /**
     * Parse the current access token into a resource.
     */
    public function parsedToken(): Token
    {
        return TokenParser::parseAccessToken(
            $this->getAccessToken()['access_token']
        );
    }

    /**
     * Retrieve the token storage implementation, initializing if needed.
     */
    public function getTokenStorage(): TokenStorage
    {
        if (! $this->storage) {
            $this->initialiseStorage();
        }

        return $this->storage;
    }

    /**
     * Inject a custom token storage implementation.
     */
    public function setTokenStorage(TokenStorage $storage): void
    {
        $this->storage = $storage;
    }

    /**
     * Initialise the default token storage from the container.
     */
    protected function initialiseStorage(): void
    {
        $this->storage = app(TokenStorage::class);
    }

    /**
     * Request an access token using the password grant.
     *
     * @param  array<string, string>  $credentials
     * @return array{access_token: string, expires_in: int, refresh_token?: string}
     */
    public function requestAccessTokenWithPassword(
        #[\SensitiveParameter]
        array $credentials
    ): array {
        return $this->requestNewAccessToken('password', $credentials);
    }

    /**
     * Request a new access token via client_credentials or password grant.
     *
     * @throws InvalidArgumentException
     */
    protected function requestNewAccessToken(
        string $grant = 'client_credentials',
        #[\SensitiveParameter]
        array $credentials = []
    ): array {
        $grant = $grant ?: static::GRANT_TYPE;

        $payload = [
            'grant_type' => $grant,
            'client_id' => $this->clientIdOverride ?? config('services.cerberus.key'),
            'client_secret' => $this->clientSecretOverride ?? config('services.cerberus.secret'),
        ];

        if ($grant === 'password') {
            if (empty($credentials['email']) || empty($credentials['password'])) {
                throw new InvalidArgumentException('Username and password are required for password grant.');
            }

            $payload['username'] = $credentials['email'];
            $payload['password'] = $credentials['password'];
        }

        $response = $this->http->post('/oauth/token', $payload);

        return $this->storeAccessTokenResponse($response->json());
    }

    /**
     * Refresh an access token using a refresh token.
     */
    protected function refreshAccessToken(?string $refreshToken): array
    {
        if (! $refreshToken) {
            return $this->requestNewAccessToken();
        }

        $this->http->withToken('');

        $response = $this->http->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientIdOverride ?? config('services.cerberus.key'),
            'client_secret' => $this->clientSecretOverride ?? config('services.cerberus.secret'),
        ]);

        return $this->storeAccessTokenResponse($response->json(), isRefresh: true);
    }

    /**
     * Store token response data and dispatch events.
     *
     * @throws RuntimeException
     */
    protected function storeAccessTokenResponse(
        array $data,
        bool $isRefresh = false
    ): array {
        if (! isset($data['access_token'], $data['expires_in'])) {
            throw new RuntimeException('Invalid access token response from Cerberus.');
        }

        $this->getTokenStorage()->put($data, $data['expires_in']);

        $token = TokenParser::parseAccessToken($data['access_token']);
        $token->setExpiresIn($data['expires_in']);

        Event::dispatch(new AccessTokenCreated(
            tokenId: $token->getTokenId(),
            userId: $token->getUserId(),
            clientId: $token->getClientId(),
        ));

        if ($isRefresh && isset($data['refresh_token'])) {
            $refresh = TokenParser::parseRefreshToken(
                $data['refresh_token'],
                $token->getTokenId()
            );
            $refresh->setExpiresIn($data['expires_in']);

            Event::dispatch(new RefreshTokenCreated(
                refreshTokenId: $refresh->getTokenId(),
                accessTokenId: $token->getTokenId(),
            ));
        }

        return $data;
    }

    /**
     * Determine if an access token is expired.
     */
    protected function isTokenExpired(array $token): bool
    {
        return TokenParser::parseAccessToken($token['access_token'])->isExpired();
    }

    /**
     * Purge the current access token from storage and optionally revoke on server.
     */
    public function purgeToken(bool $revokeOnServer = true): bool
    {
        $cached = $this->getTokenStorage()->get();

        if (! is_array($cached) || ! isset($cached['access_token'])) {
            return false;
        }

        try {
            $token = TokenParser::parseAccessToken($cached['access_token']);

            if ($revokeOnServer) {
                $this->revokeTokenOnServer($cached['access_token']);
            }

            $this->getTokenStorage()->forget();

            Event::dispatch(new AccessTokenPurged(
                tokenId: $token->getTokenId(),
                userId: $token->getUserId(),
                clientId: $token->getClientId(),
            ));

            return true;
        } catch (Throwable) {
            $this->getTokenStorage()->forget();

            return true;
        }
    }

    /**
     * Revoke a token on the authorization server.
     */
    protected function revokeTokenOnServer(string $token): bool
    {
        try {
            $response = $this->http
                ->withoutToken()
                ->post('/oauth/revoke', [
                    'token' => $token,
                    'client_id' => $this->clientIdOverride ?? config('services.cerberus.key'),
                    'client_secret' => $this->clientSecretOverride ?? config('services.cerberus.secret'),
                ]);

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Purge and regenerate a new access token.
     */
    public function forceNewToken(bool $revokeOnServer = true): array
    {
        $this->purgeToken($revokeOnServer);

        return $this->requestNewAccessToken();
    }

    /**
     * Purge all stored tokens and optionally revoke them on the server.
     */
    public function purgeAllTokens(bool $revokeOnServer = true): bool
    {
        $cached = $this->getTokenStorage()->get();

        if (is_array($cached) && isset($cached['access_token'])) {
            if ($revokeOnServer) {
                $this->revokeTokenOnServer($cached['access_token']);
            }

            try {
                $token = TokenParser::parseAccessToken($cached['access_token']);

                Event::dispatch(new TokensPurged(
                    clientId: $token->getClientId()
                ));
            } catch (Throwable) {
                Event::dispatch(new TokensPurged);
            }
        }

        $this->getTokenStorage()->forget();

        return true;
    }
}
