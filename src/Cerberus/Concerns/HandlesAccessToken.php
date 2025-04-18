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

trait HandlesAccessToken
{
    /**
     * The grant type for authentication.
     *
     * @var string
     */
    public const GRANT_TYPE = 'client_credentials';

    /**
     * The cache key for storing the client access token.
     *
     * @var string
     */
    public const CACHE_KEY_TOKEN = 'cerberus.client_access_token';

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
     * Use a specific token for authentication instead of the client credentials flow.
     *
     * @param  string  $token  The JWT token to use for authentication
     */
    public function useToken(string $token): self
    {
        // Set the token on the HTTP client
        $this->http->withToken($token);

        try {
            // Parse the token to get expiration information
            $parsedToken = TokenParser::parseAccessToken($token);
            $expiresIn = $parsedToken->getExpiresIn();

            // Store this token in the token storage so it's available for future requests
            $this->getTokenStorage()->put([
                'access_token' => $token,
                'expires_in' => $expiresIn,
            ], $expiresIn);
        } catch (Throwable $e) {
            // If we can't parse the token, still use it for this request
            // but don't store it for future requests
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
     * Request an access token using password grant.
     *
     * @param  array<string, string>  $credentials
     * @return array<string, string|int>
     *
     * @throws InvalidArgumentException
     */
    public function requestAccessTokenWithPassword(
        #[\SensitiveParameter]
        array $credentials
    ): array {
        return $this->requestNewAccessToken('password', $credentials);
    }

    /**
     * Request a new access token using client credentials or password grant.
     */
    protected function requestNewAccessToken(
        ?string $grantType = 'client_credentials',
        #[\SensitiveParameter]
        array $credentials = []
    ): array {
        if (is_null($grantType)) {
            $grantType = static::GRANT_TYPE;
        }

        $basePayload = [
            'grant_type' => $grantType,
            'client_id' => $this->clientIdOverride ?? config('services.cerberus.key'),
            'client_secret' => $this->clientSecretOverride ?? config('services.cerberus.secret'),
            'scope' => '*',
        ];

        if ($grantType === 'password') {
            $this->checkCredentials($credentials);

            $basePayload['username'] = $credentials['email'];
            $basePayload['password'] = $credentials['password'];
        }

        $response = $this->http->post('/oauth/token', $basePayload);

        return $this->storeAccessTokenResponse($response->json());
    }

    /**
     * Check the credentials for the password grant.
     *
     * @throws InvalidArgumentException
     */
    protected function checkCredentials(#[\SensitiveParameter] array $credentials = []): void
    {
        if (empty($credentials['email']) || empty($credentials['password'])) {
            throw new InvalidArgumentException('Username and password are required for password grant.');
        }
    }

    /**
     * Refresh an access token using a refresh token.
     */
    protected function refreshAccessToken(?string $refreshToken = null): array
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
            $refresh = TokenParser::parseRefreshToken(
                $data['refresh_token'],
                $token->getTokenId()
            );

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

    /**
     * Purge the current access token from storage.
     *
     * @param  bool  $revokeOnServer  Whether to also attempt revoking the token on the auth server
     * @return bool Success indicator
     */
    public function purgeToken(bool $revokeOnServer = true): bool
    {
        $cached = $this->getTokenStorage()->get();

        if (! is_array($cached) || ! isset($cached['access_token'])) {
            return false;
        }

        $token = null;
        try {
            $token = TokenParser::parseAccessToken($cached['access_token']);

            if ($revokeOnServer) {
                $this->revokeTokenOnServer($cached['access_token']);
            }

            $this->getTokenStorage()->forget();

            Event::dispatch(new AccessTokenPurged(
                tokenId: $token->getTokenId(),
                userId: $token->getUserId(),
                clientId: $token->getClientId()
            ));

            return true;
        } catch (Throwable $e) {
            // Even if we can't parse the token, still try to forget it
            $this->getTokenStorage()->forget();

            if ($token) {
                Event::dispatch(new AccessTokenPurged(
                    tokenId: $token->getTokenId(),
                    userId: $token->getUserId(),
                    clientId: $token->getClientId()
                ));
            }

            return true;
        }
    }

    /**
     * Attempt to revoke a token on the authorization server.
     *
     * @param  string  $token  The token to revoke
     * @return bool Success indicator
     */
    protected function revokeTokenOnServer(string $token): bool
    {
        try {
            $response = $this->http->withoutToken()->post('/oauth/revoke', [
                'token' => $token,
                'client_id' => $this->clientIdOverride ?? config('services.cerberus.key'),
                'client_secret' => $this->clientSecretOverride ?? config('services.cerberus.secret'),
            ]);

            return $response->successful();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Force a new token to be requested by purging the current one and requesting a new one.
     *
     * @param  bool  $revokeOnServer  Whether to also attempt revoking the token on the auth server
     * @return array The new access token data
     */
    public function forceNewToken(bool $revokeOnServer = true): array
    {
        $this->purgeToken($revokeOnServer);

        return $this->requestNewAccessToken();
    }

    /**
     * Purge all tokens from storage.
     *
     * @param  bool  $revokeOnServer  Whether to also attempt revoking tokens on the auth server
     * @return bool Success indicator
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
            } catch (Throwable $e) {
                // If we can't parse the token, still dispatch a generic event
                Event::dispatch(new TokensPurged);
            }
        }

        $this->getTokenStorage()->forget();

        return true;
    }
}
