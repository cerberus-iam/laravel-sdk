<?php

namespace Cerberus\Tests\Unit;

use App\Auth\Guards\TokenGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class TokenGuardTest extends TestCase
{
    public function test_user_returns_authenticated_user()
    {
        $user = $this->createMock(Authenticatable::class);
        $provider = $this->createMock(UserProvider::class);
        $request = $this->createMock(Request::class);

        $request->expects($this->once())
            ->method('bearerToken')
            ->willReturn('valid-token');

        $provider->expects($this->once())
            ->method('retrieveByToken')
            ->with(null, 'valid-token')
            ->willReturn($user);

        $guard = new TokenGuard($provider, $request);

        $this->assertSame($user, $guard->user());
    }

    public function test_user_returns_null_when_no_token_provided()
    {
        $provider = $this->createMock(UserProvider::class);
        $request = $this->createMock(Request::class);

        $request->expects($this->once())
            ->method('bearerToken')
            ->willReturn(null);

        // Provider should not be called when no token is provided
        $provider->expects($this->never())
            ->method('retrieveByToken');

        $guard = new TokenGuard($provider, $request);

        $this->assertNull($guard->user());
    }

    public function test_validate_returns_true_for_valid_credentials()
    {
        $user = $this->createMock(Authenticatable::class);
        $provider = $this->createMock(UserProvider::class);

        $provider->expects($this->once())
            ->method('retrieveByCredentials')
            ->with(['email' => 'test@example.com'])
            ->willReturn($user);

        $guard = new TokenGuard($provider, $this->createMock(Request::class));

        $this->assertTrue($guard->validate(['email' => 'test@example.com']));
    }

    public function test_validate_returns_false_for_invalid_credentials()
    {
        $provider = $this->createMock(UserProvider::class);

        $provider->expects($this->once())
            ->method('retrieveByCredentials')
            ->with(['email' => 'test@example.com'])
            ->willReturn(null);

        $guard = new TokenGuard($provider, $this->createMock(Request::class));

        $this->assertFalse($guard->validate(['email' => 'test@example.com']));
    }

    public function test_set_request_updates_request_instance()
    {
        $user = $this->createMock(Authenticatable::class);
        $provider = $this->createMock(UserProvider::class);
        $initialRequest = $this->createMock(Request::class);
        $newRequest = $this->createMock(Request::class);

        // Configure the new request to return a token
        $newRequest->expects($this->once())
            ->method('bearerToken')
            ->willReturn('new-token');

        // Configure the provider to return a user for the new token
        $provider->expects($this->once())
            ->method('retrieveByToken')
            ->with(null, 'new-token')
            ->willReturn($user);

        $guard = new TokenGuard($provider, $initialRequest);
        $result = $guard->setRequest($newRequest);

        // Test that setRequest returns $this for chaining
        $this->assertSame($guard, $result);

        // Test that the user method works with the new request
        $this->assertSame($user, $guard->user());
    }
}
