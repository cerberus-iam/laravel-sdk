<?php

use CerberusIAM\Auth\CerberusGuard;
use CerberusIAM\Contracts\IamClient;
use CerberusIAM\Contracts\UserRepository;
use CerberusIAM\Http\Clients\CerberusClient;
use CerberusIAM\Repositories\UserDirectoryRepository;
use Illuminate\Support\Facades\Auth;

// Set up test configuration before each test
beforeEach(function () {
    // Set a random app key for encryption
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    // Configure the cerberus guard
    config()->set('auth.guards.cerberus', [
        'driver' => 'cerberus',
        'provider' => 'users',
    ]);

    // Configure the cerberus user provider
    config()->set('auth.providers.users', [
        'driver' => 'cerberus',
    ]);
});

// Test that the service provider binds the correct implementations
it('binds the IAM client and repositories', function () {
    // Assert that the IAM client is bound to CerberusClient
    expect(app(IamClient::class))->toBeInstanceOf(CerberusClient::class);
    // Assert that the user repository is bound to UserDirectoryRepository
    expect(app(UserRepository::class))->toBeInstanceOf(UserDirectoryRepository::class);
});

// Test that the cerberus guard is registered
it('registers the cerberus guard', function () {
    $guard = Auth::guard('cerberus');

    // Assert that the guard is an instance of CerberusGuard
    expect($guard)->toBeInstanceOf(CerberusGuard::class);
});

// Test the OAuth callback handling
it('handles the OAuth callback route', function () {
    // Create a fake IAM client for testing
    $fake = new CerberusIAM\Tests\Fixtures\FakeIamClient;
    $fake->tokenResponses['code-xyz'] = [
        'access_token' => 'token-xyz',
        'refresh_token' => 'refresh-xyz',
        'expires_in' => 3600,
    ];
    $fake->userinfoResponses['token-xyz'] = [
        'id' => 'usr_789',
    ];

    // Replace the IAM client with the fake
    app()->instance(IamClient::class, $fake);

    // Set the expected state and guard name in the session
    session()->put('cerberus.oauth.cerberus.state', 'state-xyz');
    session()->put('cerberus.oauth.guard', 'cerberus');

    // Make a GET request to the callback route
    $response = $this->get('/cerberus/callback?code=code-xyz&state=state-xyz');

    // Assert that it redirects to the configured landing page
    $response->assertRedirect(config('cerberus-iam.redirect_after_login'));

    // Assert that the user is authenticated with the correct ID
    expect(Auth::guard('cerberus')->user()?->getAuthIdentifier())->toBe('usr_789');
});

// Test that the user provider can retrieve users by ID
it('retrieves users by ID through the user provider', function () {
    // Create a fake IAM client for testing
    $fake = new CerberusIAM\Tests\Fixtures\FakeIamClient;

    // Set up a mock response for getUserById
    $fake->userByIdResponses['user-123'] = [
        'id' => 'user-123',
        'email' => 'test@example.com',
        'name' => 'Test User',
        'given_name' => 'Test',
        'family_name' => 'User',
    ];

    // Replace the IAM client with the fake
    app()->instance(IamClient::class, $fake);

    // Get the user provider from the auth system
    $provider = Auth::getProvider();

    // Test retrieving a user by ID
    $user = $provider->retrieveById('user-123');

    // Assert that a user was returned
    expect($user)->not->toBeNull();
    expect($user)->toBeInstanceOf(Illuminate\Contracts\Auth\Authenticatable::class);

    // Assert user properties (PHPStan knows $user is not null after the above check)
    /* @var \Illuminate\Contracts\Auth\Authenticatable $user */
    expect($user->getAuthIdentifier())->toBe('user-123');
    expect($user->getAuthIdentifierName())->toBe('id');
});
