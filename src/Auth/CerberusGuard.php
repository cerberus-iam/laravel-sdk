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

        // Retrieve the user profile from the access token
        $profile = $this->client->getUserInfo($tokenPayload['access_token'] ?? null);

        if (! $profile) {
            throw new RuntimeException('Unable to resolve user profile from Cerberus.');
        }

        // Normalize and store the tokens with user profile
        $normalizedTokens = $this->normalizeTokenPayload($tokenPayload);
        $normalizedTokens['user_profile'] = $profile;
        $this->tokens->store($normalizedTokens);

        // Create the user instance
        $user = $this->hydrateUser($profile);

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
        // Store the state, code verifier, and guard name
        $this->stateStore->putState($state, $codeVerifier, $this->name);

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
            // Refresh the access token
            $fresh = $this->client->refreshAccessToken($stored['refresh_token']);

            // Fetch fresh user profile
            $profile = $this->client->getUserInfo($fresh['access_token'] ?? null);

            // Store refreshed tokens with updated profile
            $stored = $this->normalizeTokenPayload($fresh);
            $stored['user_profile'] = $profile;
            $this->tokens->store($stored);
        }

        // Try to get user from stored profile first (fast path)
        if (! empty($stored['user_profile'])) {
            return $this->hydrateUser($stored['user_profile']);
        }

        // Fallback: fetch user from IAM API (slow path, for backwards compatibility)
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

        return $profile ? $this->hydrateUser($profile) : null;
    }

    protected function retrieveUserFromAccessToken(?string $accessToken): ?Authenticatable
    {
        if (! $accessToken) {
            return null;
        }

        $profile = $this->client->getUserInfo($accessToken);

        return $profile ? $this->hydrateUser($profile) : null;
    }

    /**
     * Create a user instance from profile data.
     *
     * This method handles both database-backed (Eloquent) and stateless authentication.
     * For database-backed mode, it syncs the user to the local database.
     * For stateless mode, it creates a CerberusUser value object.
     *
     * @param  array<string, mixed>  $profile  The user profile data from Cerberus.
     * @return Authenticatable The user instance.
     */
    protected function hydrateUser(array $profile): Authenticatable
    {
        // If the provider supports syncing, sync the user to the local database
        if ($this->provider instanceof EloquentCerberusUserProvider) {
            return $this->provider->syncUser($profile);
        }

        // Otherwise, return a stateless CerberusUser instance
        return CerberusUser::fromProfile($profile);
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
