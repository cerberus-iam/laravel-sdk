<?php

namespace Cerberus\Tests\Unit;

use Cerberus\Guards\TokenGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class TokenGuardTest extends TestCase
{
    /**
     * @test
     */
    public function user_returns_authenticated_user()
    {
        $user = $this->createMock(Authenticatable::class);
        $provider = $this->createMock(UserProvider::class);

        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer valid-token',
        ]);

        $provider->expects($this->once())
            ->method('retrieveByToken')
            ->with(null, 'valid-token')
            ->willReturn($user);

        $guard = new TokenGuard($provider, $request);

        $this->assertSame($user, $guard->user());
    }

    /**
     * @test
     */
    public function user_returns_null_when_no_token_provided()
    {
        $provider = $this->createMock(UserProvider::class);

        $request = Request::create('/', 'GET');

        // Provider should not be called when no token is provided
        $provider->expects($this->never())
            ->method('retrieveByToken');

        $guard = new TokenGuard($provider, $request);

        $this->assertNull($guard->user());
    }

    /**
     * @test
     */
    public function validate_returns_true_for_valid_credentials()
    {
        $user = $this->createMock(Authenticatable::class);
        $provider = $this->createMock(UserProvider::class);

        $provider->expects($this->once())
            ->method('retrieveByCredentials')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        $request = Request::create('/', 'POST');

        $guard = new TokenGuard($provider, $request);

        $this->assertTrue($guard->validate(['email' => 'test@example.com']));
    }

    /**
     * @test
     */
    public function validate_returns_false_for_invalid_credentials()
    {
        $provider = $this->createMock(UserProvider::class);

        $provider->expects($this->once())
            ->method('retrieveByCredentials')
            ->with(['email' => 'test@example.com'])
            ->willReturn(null);

        $request = Request::create('/', 'POST');

        $guard = new TokenGuard($provider, $request);

        $this->assertFalse($guard->validate(['email' => 'test@example.com']));
    }

    /**
     * @test
     */
    public function set_request_updates_request_instance()
    {
        $user = $this->createMock(Authenticatable::class);
        $provider = $this->createMock(UserProvider::class);

        $initialRequest = Request::create('/', 'GET');
        $newRequest = Request::create('/', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer new-token',
        ]);

        $provider->expects($this->once())
            ->method('retrieveByToken')
            ->with(null, 'new-token')
            ->willReturn($user);

        $guard = new TokenGuard($provider, $initialRequest);
        $result = $guard->setRequest($newRequest);

        $this->assertSame($guard, $result);
        $this->assertSame($user, $guard->user());
    }
}
