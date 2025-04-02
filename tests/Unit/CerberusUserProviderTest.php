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

        Cache::shouldReceive('remember')
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

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

        $this->cerberus->shouldReceive('getHttpClient->withToken')
            ->with($token);

        $this->cerberus->shouldReceive('auth->findByToken')
            ->with($token)
            ->andReturn($userMock);

        $result = $this->userProvider->retrieveByToken($identifier, $token);

        $this->assertSame($userMock, $result);
    }

    public function test_update_remember_token(): void
    {
        $userMock = m::mock(Authenticatable::class);
        $token = 'new-token';

        $userMock->shouldReceive('getAuthIdentifierName')->andReturn('email');
        $userMock->shouldReceive('getEmailForPasswordReset')->andReturn('user@example.com');
        $userMock->shouldReceive('getAuthIdentifier')->andReturn('user@example.com');

        $this->cerberus->shouldReceive('users->where->first->update')
            ->with(['remember_token' => $token]);

        $this->userProvider->updateRememberToken($userMock, $token);

        $this->assertTrue(true);
    }

    public function test_retrieve_by_credentials(): void
    {
        $credentials = ['email' => 'user@example.com'];
        $userMock = m::mock(Authenticatable::class);
        $queryBuilderMock = m::mock();

        $this->cerberus->shouldReceive('users')
            ->andReturn($queryBuilderMock);

        $queryBuilderMock->shouldReceive('where')
            ->with('email', 'user@example.com')
            ->andReturn($queryBuilderMock);

        $queryBuilderMock->shouldReceive('first')
            ->andReturn($userMock);

        $result = $this->userProvider->retrieveByCredentials($credentials);

        $this->assertSame($userMock, $result);
    }

    public function test_validate_credentials(): void
    {
        $userMock = m::mock(Authenticatable::class);
        $credentials = ['password' => 'secret'];

        $this->cerberus->shouldReceive('auth->user->checkPassword')
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

        $this->cerberus->shouldReceive('auth->user->rehashPasswordIfRequired')
            ->with($credentials, $force)
            ->andReturn(true);

        $result = $this->userProvider->rehashPasswordIfRequired($userMock, $credentials, $force);

        $this->assertTrue($result);
    }

    public function test_get_connection(): void
    {
        $result = $this->userProvider->getConnection();

        $this->assertSame($this->cerberus, $result);
    }
}
