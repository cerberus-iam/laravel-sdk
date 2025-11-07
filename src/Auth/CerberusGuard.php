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

    /**
     * Determine if the current user is authenticated.
     *
     * @return bool True if a user is authenticated, false otherwise
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Determine if the current user is a guest (not authenticated).
     *
     * @return bool True if no user is authenticated, false otherwise
     */
    public function guest(): bool
    {
        return ! $this->check();
    }

    /**
     * Get the currently authenticated user.
     *
     * Attempts to resolve the user from stored OAuth tokens or session cookies.
     * Returns null if no authenticated user is found.
     *
     * @return Authenticatable|null The authenticated user or null
     */
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

    /**
     * Get the ID of the currently authenticated user.
     *
     * @return mixed The user ID or null if not authenticated
     */
    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * Validate the given credentials (not supported for OAuth).
     *
     * This method exists for interface compatibility but always returns
     * the current authentication status since OAuth doesn't use passwords.
     *
     * @param  array<string, mixed>  $credentials  Ignored for OAuth authentication
     * @return bool True if authenticated, false otherwise
     */
    public function validate(array $credentials = []): bool
    {
        return $this->check();
    }

    /**
     * Set the current authenticated user.
     *
     * @param  Authenticatable  $user  The user to set as authenticated
     */
    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
    }

    /**
     * Determine if a user has been set on this guard instance.
     *
     * @return bool True if a user is set, false otherwise
     */
    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    /**
     * Determine if the user was authenticated via "remember me" cookie.
     *
     * Cerberus IAM does not support Laravel's remember-me functionality.
     *
     * @return bool Always returns false
     */
    public function viaRemember(): bool
    {
        // Cerberus IAM guard does not support Laravel's remember-me cookies.
        return false;
    }

    /**
     * Log a user into the application.
     *
     * Sets the given user as the currently authenticated user.
     * The remember parameter is ignored as OAuth handles session persistence.
     *
     * @param  Authenticatable  $user  The user to authenticate
     * @param  bool  $remember  Ignored for OAuth authentication
     */
    public function login(Authenticatable $user, $remember = false): void
    {
        $this->setUser($user);
    }

    /**
     * Log a user into the application by their ID.
     *
     * Retrieves the user by ID from the user provider and authenticates them.
     *
     * @param  mixed  $id  The user ID
     * @param  bool  $remember  Ignored for OAuth authentication
     * @return Authenticatable|null The authenticated user or null if not found
     */
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

    /**
     * Log a user into the application for a single request.
     *
     * For OAuth authentication, this behaves the same as checking current authentication
     * since OAuth sessions are managed externally.
     *
     * @param  array<string, mixed>  $credentials  Ignored for OAuth authentication
     * @return bool True if authenticated, false otherwise
     */
    public function once(array $credentials = []): bool
    {
        return $this->check();
    }

    /**
     * Log a user into the application for a single request by their ID.
     *
     * @param  mixed  $id  The user ID
     * @return Authenticatable|null The authenticated user or null if not found
     */
    public function onceUsingId($id): ?Authenticatable
    {
        return $this->loginUsingId($id);
    }

    /**
     * Attempt to authenticate a user using the given credentials.
     *
     * Password-based authentication is not supported. This method throws an exception
     * directing users to use OAuth flow instead.
     *
     * @param  array<string, mixed>  $credentials  Ignored - password auth not supported
     * @param  bool  $remember  Ignored - password auth not supported
     * @return bool Never returns - always throws exception
     *
     * @throws BadMethodCallException Always thrown as password auth is disabled
     */
    public function attempt(array $credentials = [], $remember = false): bool
    {
        throw new BadMethodCallException('Password-based authentication is disabled. Redirect to Cerberus for sign-in.');
    }

    /**
     * Attempt to authenticate a user with conditions using the given credentials.
     *
     * Delegates to the attempt method. Callbacks are ignored since password
     * authentication is not supported.
     *
     * @param  array<string, mixed>  $credentials  Ignored - password auth not supported
     * @param  array<callable>  $callbacks  Ignored - password auth not supported
     * @param  bool  $remember  Ignored - password auth not supported
     * @return bool Never returns - always throws exception
     *
     * @throws BadMethodCallException Always thrown as password auth is disabled
     */
    public function attemptWhen(array $credentials, array $callbacks, $remember = false): bool
    {
        return $this->attempt($credentials, $remember);
    }

    /**
     * Complete OAuth authentication using an authorization code.
     *
     * Exchanges the authorization code for tokens, validates the OAuth state,
     * retrieves the user profile, and authenticates the user.
     *
     * @param  string  $code  The authorization code from the OAuth callback
     * @param  string  $state  The state parameter for CSRF protection
     * @param  string|null  $codeVerifier  The PKCE code verifier (optional if stored)
     * @return Authenticatable The authenticated user instance
     *
     * @throws RuntimeException When state validation fails or user profile cannot be retrieved
     */
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

    /**
     * Log the current user out of the application.
     *
     * Revokes OAuth tokens, clears stored tokens, logs out the session,
     * and clears the authenticated user from the guard.
     */
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

    /**
     * Redirect the user to the Cerberus IAM authorization endpoint.
     *
     * Generates OAuth state and PKCE parameters, stores them securely,
     * and returns a redirect response to initiate the OAuth flow.
     *
     * @param  string|null  $returnTo  Optional URL to redirect to after authentication
     * @return Response Redirect response to the Cerberus authorization URL
     */
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

    /**
     * Get the token store instance used by this guard.
     *
     * @return TokenStore The token storage implementation
     */
    public function getTokenStore(): TokenStore
    {
        return $this->tokens;
    }

    /**
     * Set the current HTTP request instance.
     *
     * @param  Request  $request  The HTTP request instance
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Attempt to resolve the authenticated user from stored OAuth tokens.
     *
     * Checks for valid stored tokens, refreshes expired tokens if possible,
     * and hydrates the user from the stored or fetched profile data.
     *
     * @return Authenticatable|null The authenticated user or null if tokens are invalid
     */
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

    /**
     * Attempt to resolve the authenticated user from a session cookie.
     *
     * Checks for a valid session cookie and retrieves the user profile
     * from the session-based authentication endpoint.
     *
     * @return Authenticatable|null The authenticated user or null if session is invalid
     */
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

    /**
     * Retrieve user information using an access token.
     *
     * Fetches the user profile from the OAuth userinfo endpoint
     * using the provided access token.
     *
     * @param  string|null  $accessToken  The access token for authentication
     * @return Authenticatable|null The user instance or null if token is invalid
     */
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

    /**
     * Get the default OAuth scopes for authentication.
     *
     * Returns the standard OpenID Connect scopes required for user authentication.
     *
     * @return array<string> Array of default scope strings
     */
    protected function defaultScopes(): array
    {
        return ['openid', 'profile', 'email'];
    }
}
