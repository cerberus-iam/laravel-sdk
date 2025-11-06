<?php

declare(strict_types=1);

namespace CerberusIAM\Auth;

use BadMethodCallException;
use CerberusIAM\Contracts\IamClient;
use CerberusIAM\Contracts\OAuthStateStore;
use CerberusIAM\Contracts\TokenStore;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cerberus Authentication Guard
 *
 * This class implements Laravel's StatefulGuard interface, providing OAuth-based
 * authentication using the Cerberus IAM service.
 */
class CerberusGuard implements StatefulGuard
{
    use GuardHelpers;

    /**
     * The guard configuration.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    protected ?Request $request;

    /**
     * Create a new Cerberus guard instance.
     *
     * @param  string  $name  The name of the guard.
     * @param  IamClient  $client  The IAM client for API communication.
     * @param  UserProvider|null  $provider  The user provider for retrieving users.
     * @param  TokenStore  $tokens  The store for OAuth tokens.
     * @param  OAuthStateStore  $stateStore  The store for OAuth state and code verifier.
     * @param  Request  $request  The current HTTP request.
     * @param  array<string, mixed>  $config  The guard configuration options.
     */
    public function __construct(
        protected string $name,
        protected IamClient $client,
        ?UserProvider $provider,
        protected TokenStore $tokens,
        protected OAuthStateStore $stateStore,
        Request $request,
        array $config = []
    ) {
        $this->provider = $provider;
        $this->request = $request;
        $this->config = $config;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        if ($user = $this->resolveUserFromStoredTokens()) {
            return $this->user = $user;
        }

        if ($user = $this->resolveUserFromSessionCookie()) {
            return $this->user = $user;
        }

        return null;
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        return $this->check();
    }

    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function viaRemember(): bool
    {
        // Cerberus IAM guard does not support Laravel's remember-me cookies.
        return false;
    }

    public function login(Authenticatable $user, $remember = false): void
    {
        $this->setUser($user);
    }

    public function loginUsingId($id, $remember = false): ?Authenticatable
    {
        if (! $this->provider) {
            return null;
        }

        $user = $this->provider->retrieveById($id);

        if ($user) {
            $this->login($user, $remember);
        }

        return $user;
    }

    public function once(array $credentials = []): bool
    {
        return $this->check();
    }

    public function onceUsingId($id): ?Authenticatable
    {
        return $this->loginUsingId($id);
    }

    public function attempt(array $credentials = [], $remember = false): bool
    {
        throw new BadMethodCallException('Password-based authentication is disabled. Redirect to Cerberus for sign-in.');
    }

    public function attemptWhen(array $credentials, array $callbacks, $remember = false): bool
    {
        return $this->attempt($credentials, $remember);
    }

    public function loginFromAuthorizationCode(string $code, string $state, ?string $codeVerifier = null): Authenticatable
    {
        // Retrieve and validate the expected state
        $expected = $this->stateStore->pullState();

        if (! $expected['state'] || ! hash_equals($expected['state'], $state)) {
            throw new RuntimeException('Invalid Cerberus OAuth state.');
        }

        // Use provided code verifier or the stored one
        $codeVerifier ??= $expected['code_verifier'];

        // Exchange the authorization code for tokens
        $tokenPayload = $this->client->exchangeAuthorizationCode($code, $codeVerifier);

        // Normalize and store the tokens
        $normalizedTokens = $this->normalizeTokenPayload($tokenPayload);
        $this->tokens->store($normalizedTokens);

        // Retrieve the user from the access token
        $user = $this->retrieveUserFromAccessToken($normalizedTokens['access_token'] ?? null);

        if (! $user) {
            throw new RuntimeException('Unable to resolve user profile from Cerberus.');
        }

        // Set the authenticated user
        $this->setUser($user);

        return $user;
    }

    public function logout(): void
    {
        // Retrieve stored tokens
        $stored = $this->tokens->retrieve();

        // Revoke tokens if they exist
        if ($stored) {
            $this->client->revokeTokens($stored['access_token'] ?? null, $stored['refresh_token'] ?? null);
        }

        // Clear stored tokens
        $this->tokens->clear();

        // Get session cookie name and value
        $cookieName = $this->client->sessionCookieName();
        $sessionToken = $cookieName ? $this->request->cookies->get($cookieName) : null;

        // Logout session if session token exists
        if ($sessionToken) {
            $this->client->logoutSession($sessionToken);
            Cookie::queue(Cookie::forget($cookieName));
        }

        // Clear the authenticated user
        $this->user = null;
    }

    public function redirectToCerberus(?string $returnTo = null): Response
    {
        // Generate a unique state for CSRF protection
        $state = Str::uuid()->toString();
        // Generate a code verifier for PKCE
        $codeVerifier = $this->client->generateCodeVerifier();
        // Store the state and code verifier
        $this->stateStore->putState($state, $codeVerifier);

        // Build the authorization URL
        $authUrl = $this->client->buildAuthorizationUrl($state, $codeVerifier, $returnTo ?? ($this->request?->fullUrl() ?? null));

        // Return a redirect response to the authorization URL
        return new RedirectResponse($authUrl);
    }

    public function getTokenStore(): TokenStore
    {
        return $this->tokens;
    }

    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    protected function resolveUserFromStoredTokens(): ?Authenticatable
    {
        // Retrieve stored tokens
        $stored = $this->tokens->retrieve();

        if (! $stored) {
            return null;
        }

        $accessToken = $stored['access_token'] ?? null;

        if (! $accessToken) {
            return null;
        }

        // Check if token is expired and refresh if possible
        $expiresAt = isset($stored['expires_at']) ? Carbon::parse($stored['expires_at']) : null;

        if ($expiresAt && $expiresAt->isPast() && ! empty($stored['refresh_token'])) {
            $fresh = $this->client->refreshAccessToken($stored['refresh_token']);
            $stored = $this->normalizeTokenPayload($fresh);
            $this->tokens->store($stored);
            $accessToken = $stored['access_token'] ?? null;
        }

        if (! $accessToken) {
            return null;
        }

        // Retrieve user from the access token
        return $this->retrieveUserFromAccessToken($accessToken);
    }

    protected function resolveUserFromSessionCookie(): ?Authenticatable
    {
        $cookieName = $this->client->sessionCookieName();
        if (! $cookieName) {
            return null;
        }

        $cookieValue = $this->request->cookies->get($cookieName);

        if (! $cookieValue) {
            return null;
        }

        $profile = $this->client->getCurrentUserFromSession($cookieValue);

        return $profile ? CerberusUser::fromProfile($profile) : null;
    }

    protected function retrieveUserFromAccessToken(?string $accessToken): ?Authenticatable
    {
        if (! $accessToken) {
            return null;
        }

        $profile = $this->client->getUserInfo($accessToken);

        return $profile ? CerberusUser::fromProfile($profile) : null;
    }

    /**
     * Normalise token payloads so expirations are stored as ISO strings.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizeTokenPayload(array $payload): array
    {
        $tokens = $payload;

        // Convert expires_in to expires_at as ISO string
        if (isset($tokens['expires_in'])) {
            $tokens['expires_at'] = Carbon::now()->addSeconds((int) $tokens['expires_in'])->toIso8601String();
        }

        // Set default scopes if not provided
        if (! isset($tokens['scope'])) {
            $tokens['scope'] = implode(' ', Arr::wrap($this->config['scopes'] ?? $this->defaultScopes()));
        }

        return $tokens;
    }

    protected function defaultScopes(): array
    {
        return ['openid', 'profile', 'email'];
    }
}
