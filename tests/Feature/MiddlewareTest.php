<?php

use CerberusIAM\Contracts\IamClient;
use CerberusIAM\Middleware\EnsureCerberusAuthenticated;
use CerberusIAM\Tests\Fixtures\FakeIamClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Middleware Tests
 *
 * Tests the EnsureCerberusAuthenticated middleware, which enforces authentication
 * by redirecting unauthenticated users to the Cerberus OAuth flow.
 */

// Set up test configuration and fake IAM client before each test
beforeEach(function () {
    // Configure the cerberus auth guard
    config()->set('auth.guards.cerberus', [
        'driver' => 'cerberus',
        'provider' => 'cerberus-users',
    ]);

    // Configure the cerberus user provider
    config()->set('auth.providers.cerberus-users', [
        'driver' => 'cerberus',
    ]);

    // Bind fake IAM client to container for testing
    app()->instance(IamClient::class, new FakeIamClient);
});

// Test that unauthenticated users are redirected to Cerberus
it('redirects unauthenticated users to Cerberus', function () {
    // Resolve the middleware from the container
    $middleware = app(EnsureCerberusAuthenticated::class);

    // Create a request to a protected route
    $request = Request::create('/dashboard', 'GET');

    // Call the middleware handle method
    $response = $middleware->handle($request, fn () => response('next'));

    // Assert that the response is a redirect (to Cerberus OAuth)
    expect($response->isRedirect())->toBeTrue();
});

// Test that authenticated users are allowed to proceed
it('allows authenticated users through', function () {
    // Get the cerberus guard
    $guard = Auth::guard('cerberus');

    // Create and set an authenticated user
    $user = new \CerberusIAM\Auth\CerberusUser(['id' => 'usr_001']);
    $guard->setUser($user);

    // Resolve the middleware
    $middleware = app(EnsureCerberusAuthenticated::class);
    // Create a request
    $request = Request::create('/dashboard', 'GET');

    // Call the middleware
    $response = $middleware->handle($request, fn () => response('ok'));

    // Assert that the response contains the expected content (middleware passed)
    expect($response->getContent())->toBe('ok');
});
