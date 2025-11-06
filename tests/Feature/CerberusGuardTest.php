<?php

use CerberusIAM\Auth\CerberusGuard;
use CerberusIAM\Contracts\IamClient;
use CerberusIAM\Support\Stores\SessionTokenStore;
use CerberusIAM\Tests\Fixtures\FakeIamClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Cerberus Guard Tests
 *
 * Tests the CerberusGuard authentication implementation, covering OAuth flow,
 * token management, user resolution, and logout functionality.
 */

// Set up test configuration before each test
beforeEach(function () {
    // Configure the cerberus auth guard
    config()->set('auth.guards.cerberus', [
        'driver' => 'cerberus',
        'provider' => 'cerberus-users',
        'scopes' => ['openid', 'profile', 'email'],
    ]);

    // Configure the cerberus user provider
    config()->set('auth.providers.cerberus-users', [
        'driver' => 'cerberus',
    ]);
});

/**
 * Helper function to swap the IAM client in the container for testing.
 *
 * @param  FakeIamClient  $fake  The fake IAM client to use.
 */
function swapIamClient(FakeIamClient $fake): void
{
    app()->instance(IamClient::class, $fake);
}

// Test that redirecting to Cerberus stores OAuth state in session
it('redirects to Cerberus and stores oauth state', function () {
    // Create and swap in a fake IAM client
    $fake = new FakeIamClient;
    swapIamClient($fake);

    // Get the cerberus guard instance
    /** @var CerberusGuard $guard */
    $guard = Auth::guard('cerberus');

    // Call redirect method with return URL
    $response = $guard->redirectToCerberus('https://app.test/dashboard');

    // Assert that a redirect response is returned
    expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\Response::class)
        ->and($response->isRedirect())->toBeTrue();

    // Verify that OAuth state was stored in session
    $sessionState = Session::get('cerberus.oauth.cerberus.state');
    expect($sessionState)->not->toBeNull();
});

// Test login from authorization code stores user and tokens
it('logs in from authorization code and stores the user', function () {
    // Set up fake client with mock responses
    $fake = new FakeIamClient;
    $fake->tokenResponses['code-123'] = [
        'access_token' => 'access-123',
        'refresh_token' => 'refresh-123',
        'expires_in' => 3600,
        'token_type' => 'Bearer',
    ];
    $fake->userinfoResponses['access-123'] = [
        'id' => 'usr_123',
        'email' => 'jane@example.com',
        'name' => 'Jane Doe',
    ];

    // Swap in the fake client
    swapIamClient($fake);

    // Get the guard
    /** @var CerberusGuard $guard */
    $guard = Auth::guard('cerberus');

    // Initialize OAuth state by calling redirect first
    $guard->redirectToCerberus();
    $state = Session::get('cerberus.oauth.cerberus.state');

    // Perform login with authorization code
    $user = $guard->loginFromAuthorizationCode('code-123', $state);

    // Assert user was created with correct ID
    expect($user->getAuthIdentifier())->toBe('usr_123');

    // Verify tokens were stored in session
    $tokens = (new SessionTokenStore(app('session.store'), 'cerberus.tokens.cerberus'))->retrieve();
    expect($tokens['access_token'])->toBe('access-123');
});

// Test that expired tokens are automatically refreshed when fetching user
it('refreshes expired tokens when fetching the user', function () {
    // Set up fake client with refresh response
    $fake = new FakeIamClient;
    $fake->refreshResponses['refresh-old'] = [
        'access_token' => 'access-new',
        'refresh_token' => 'refresh-old',
        'expires_in' => 3600,
    ];
    $fake->userinfoResponses['access-new'] = [
        'id' => 'usr_999',
        'email' => 'new@example.com',
    ];

    // Swap in the fake client
    swapIamClient($fake);

    // Get the guard
    /** @var CerberusGuard $guard */
    $guard = Auth::guard('cerberus');

    // Store expired tokens
    $guard->getTokenStore()->store([
        'access_token' => 'access-old',
        'refresh_token' => 'refresh-old',
        'expires_at' => now()->subMinute()->toIso8601String(), // Expired
    ]);

    // Fetch user, which should trigger token refresh
    $user = $guard->user();

    // Assert the user from refreshed tokens is returned
    expect($user?->getAuthIdentifier())->toBe('usr_999');
});

// Test logout revokes tokens and logs out session
it('logs out and revokes tokens', function () {
    // Create fake client
    $fake = new FakeIamClient;
    swapIamClient($fake);

    // Get the guard
    /** @var CerberusGuard $guard */
    $guard = Auth::guard('cerberus');

    // Store valid tokens
    $guard->getTokenStore()->store([
        'access_token' => 'access-old',
        'refresh_token' => 'refresh-old',
        'expires_at' => now()->addMinute()->toIso8601String(),
    ]);

    // Set session cookie
    request()->cookies->set('cerb_sid', 'cookie123');

    // Perform logout
    $guard->logout();

    // Assert tokens were revoked and session was logged out
    expect($fake->revocations)->toContain(['access-old', 'refresh-old'])
        ->and($fake->logoutCalls)->toContain('cookie123');
});
