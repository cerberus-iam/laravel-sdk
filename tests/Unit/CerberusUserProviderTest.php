<?php

namespace Tests\Unit;

use Cerberus\Cerberus;
use Cerberus\CerberusUserProvider;
use Cerberus\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Mockery as m;

class CerberusUserProviderTest extends TestCase
{
    protected $cerberus;

    protected $userProvider;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent any real caching
        Cache::shouldReceive('remember')
            ->andReturnUsing(fn ($key, $ttl, $cb) => $cb());
        Cache::shouldReceive('forget');

        $this->cerberus = m::mock(Cerberus::class);

        $this->userProvider = new CerberusUserProvider($this->cerberus);
    }

    protected function tearDown(): void
    {
        m::close();
        restore_error_handler();
        restore_exception_handler();
    }

    public function test_retrieve_by_id(): void
    {
        $userId = 1;
        $userMock = m::mock(Authenticatable::class);

        $this->cerberus->shouldReceive('users->find')
            ->with($userId)
            ->andReturn($userMock);

        $result = $this->userProvider->retrieveById($userId);
        $this->assertSame($userMock, $result);
    }

    public function test_retrieve_by_token(): void
    {
        $identifier = 1;
        $token = 'test-token';
        $userMock = m::mock(Authenticatable::class);
        $authMock = m::mock();

        // should useToken() instead of getHttpClient
        $this->cerberus->shouldReceive('useToken')
            ->with($token)
            ->once();

        $this->cerberus->shouldReceive('auth')
            ->andReturn($authMock);
        $authMock->shouldReceive('findByToken')
            ->with($token)
            ->andReturn($userMock);

        $result = $this->userProvider->retrieveByToken($identifier, $token);
        $this->assertSame($userMock, $result);
    }

    public function test_update_remember_token(): void
    {
        $userMock = m::mock(Authenticatable::class);
        $token = 'new-token';

        $userMock->shouldReceive('getAuthIdentifierName')
            ->andReturn('email');
        $userMock->shouldReceive('getAuthIdentifier')
            ->andReturn('user@example.com');

        $qb = m::mock();
        $this->cerberus->shouldReceive('users')
            ->andReturn($qb);

        $qb->shouldReceive('where')
            ->with('email', 'user@example.com')
            ->andReturn($qb);
        $qb->shouldReceive('update')
            ->with(['remember_token' => $token])
            ->once();

        // No exception means success
        $this->userProvider->updateRememberToken($userMock, $token);
        $this->assertTrue(true);
    }

    public function test_retrieve_by_credentials_with_email_only(): void
    {
        $credentials = ['email' => 'user@example.com'];
        $userMock = m::mock(Authenticatable::class);
        $qb = m::mock();

        $this->cerberus->shouldReceive('users')
            ->andReturn($qb);
        $qb->shouldReceive('where')
            ->with('email', 'user@example.com')
            ->andReturn($qb);
        $qb->shouldReceive('first')
            ->andReturn($userMock);

        $result = $this->userProvider->retrieveByCredentials($credentials);
        $this->assertSame($userMock, $result);
    }

    public function test_validate_credentials(): void
    {
        $userMock = m::mock(Authenticatable::class);
        $credentials = ['password' => 'secret'];
        $authMock = m::mock();

        $this->cerberus->shouldReceive('auth')
            ->andReturn($authMock);
        $authMock->shouldReceive('user')
            ->with($userMock)
            ->andReturnSelf();
        $authMock->shouldReceive('checkPassword')
            ->with($credentials)
            ->andReturn(true);

        $result = $this->userProvider->validateCredentials($userMock, $credentials);
        $this->assertTrue($result);
    }

    public function test_rehash_password_if_required(): void
    {
        $userMock = m::mock(Authenticatable::class);
        $credentials = ['password' => 'secret'];
        $force = false;
        $authMock = m::mock();

        $this->cerberus->shouldReceive('auth')
            ->andReturn($authMock);
        $authMock->shouldReceive('user')
            ->with($userMock)
            ->andReturnSelf();
        $authMock->shouldReceive('rehashPasswordIfRequired')
            ->with($credentials, $force)
            ->andReturn(true);

        $result = $this->userProvider->rehashPasswordIfRequired($userMock, $credentials, $force);
        $this->assertTrue($result);
    }

    public function test_get_connection(): void
    {
        $this->assertSame($this->cerberus, $this->userProvider->getConnection());
    }
}
