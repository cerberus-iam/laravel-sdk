<?php

namespace Cerberus\Tests\Feature;

use Cerberus\Cerberus;
use Cerberus\CerberusUserProvider;
use Cerberus\Contracts\TokenStorage;
use Cerberus\Guards\TokenGuard;
use Cerberus\Resources\User;
use Cerberus\Tests\TestCase;
use Fetch\Http\Response;
use Fetch\Interfaces\ClientHandler as ClientHandlerInterface;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Mockery as m;

class AuthenticatedSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Auth::extend('cerberus', function ($app, $name, array $config) {
            return new TokenGuard(
                $app['request'],
                $app->make(UserProvider::class),
                $app['cache.store'],
                $app['config']['cerberus.auth.token_cache_prefix']
            );
        });

        // Setup a test route protected by auth
        Route::get('/protected', function (Request $request) {
            return response()->json(['user' => $request->user()]);
        })->middleware('auth:cerberus');
    }

    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    public function test_authenticated_session_returns_user(): void
    {
        $mockToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.'.
             'eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IlRlc3QiLCJpYXQiOjE1MTYyMzkwMjJ9.'.
             'SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';

        $mockHandler = m::mock(ClientHandlerInterface::class);
        app()->bind(ClientHandlerInterface::class, fn () => $mockHandler);
        $mockHandler->shouldReceive('hasHeader')->with('Authorization')->andReturn(false);
        $mockHandler->shouldReceive('getCurrentAccessToken')->andReturn($mockToken);
        $mockHandler->shouldReceive('withToken')->with($mockToken)->andReturnSelf();
        $mockHandler->shouldReceive('get')
            ->with('/user')
            ->andReturn(new Response(200, [], json_encode([
                'uid' => 1,
                'email' => 'test@example.com',
                'first_name' => 'Test',
            ])));

        $mockStorage = m::mock(TokenStorage::class);
        $mockStorage->shouldReceive('retrieve')->andReturn($mockToken);
        $mockStorage->shouldReceive('get')->andReturn([
            'access_token' => $mockToken,
            'refresh_token' => 'dummy-refresh-token',
            'expires_at' => now()->addHour()->timestamp,
        ]);
        $mockStorage->shouldReceive('put')
            ->with([
                'access_token' => $mockToken,
                'refresh_token' => 'dummy-refresh-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 3600)
            ->andReturnNull();

        $mockHandler->shouldReceive('post')
            ->with('/oauth/token', m::type('array'))
            ->andReturn(new Response(200, [], json_encode([
                'access_token' => $mockToken,
                'refresh_token' => 'dummy-refresh-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ])));

        $mockUser = new User([
            'uid' => 1,
            'email' => 'test@example.com',
            'first_name' => 'Test',
        ]);

        $mockHandler->shouldReceive('getAuthenticatedUser')->andReturn($mockUser);

        $this->app->instance(ClientHandlerInterface::class, $mockHandler);
        $this->app->instance(TokenStorage::class, $mockStorage);

        config([
            'auth.guards.cerberus' => [
                'driver' => 'cerberus',
                'provider' => 'cerberus',
            ],
            'auth.providers.cerberus' => [
                'driver' => 'cerberus',
            ],
        ]);

        Auth::provider('cerberus', function ($app, array $config) {
            return new CerberusUserProvider(
                $app->make(Cerberus::class)
            );
        });

        Auth::extend('cerberus', function ($app, $name, array $config) {
            return new TokenGuard(
                Auth::createUserProvider($config['provider']),
                $app['request']
            );
        });

        $this->withHeaders([
            'Authorization' => 'Bearer '.$mockToken,
        ]);

        $response = $this->getJson('/protected');

        $response->assertOk();
        $response->assertJson([
            'user' => [
                'uid' => 1,
                'email' => 'test@example.com',
                'first_name' => 'Test',
            ],
        ]);
    }
}
