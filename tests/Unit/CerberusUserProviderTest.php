<?php

namespace Tests\Unit;

use Cerberus\Cerberus;
use Cerberus\CerberusUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Mockery;
use PHPUnit\Framework\TestCase;

class CerberusUserProviderTest extends TestCase
{
    protected $cerberus;

    protected $userProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cerberus = Mockery::mock(Cerberus::class);
        $this->userProvider = new CerberusUserProvider($this->cerberus);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * @test
     */
    public function retrieve_by_id()
    {
        $userId = 1;
        $userMock = Mockery::mock(Authenticatable::class);

        $this->cerberus->shouldReceive('users->find')
            ->with($userId)
            ->andReturn($userMock);

        $result = $this->userProvider->retrieveById($userId);

        $this->assertSame($userMock, $result);
    }

    /**
     * @test
     */
    public function retrieve_by_token()
    {
        $identifier = 1;
        $token = 'test-token';
        $userMock = Mockery::mock(Authenticatable::class);

        $this->cerberus->shouldReceive('getHttpClient->withToken')
            ->with($token);
        $this->cerberus->shouldReceive('auth->findByToken')
            ->with($token)
            ->andReturn($userMock);

        $result = $this->userProvider->retrieveByToken($identifier, $token);

        $this->assertSame($userMock, $result);
    }

    /**
     * @test
     */
    public function update_remember_token()
    {
        $userMock = Mockery::mock(Authenticatable::class);
        $token = 'new-token';

        $userMock->shouldReceive('getAuthIdentifierName')
            ->andReturn('email');
        $userMock->shouldReceive('getEmailForPasswordReset')
            ->andReturn('user@example.com');

        $this->cerberus->shouldReceive('users->where->first->update')
            ->with(['remember_token' => $token]);

        $this->userProvider->updateRememberToken($userMock, $token);

        $this->assertTrue(true); // No exception means success
    }

    /**
     * @test
     */
    public function retrieve_by_credentials()
    {
        $credentials = ['email' => 'user@example.com'];
        $userMock = Mockery::mock(Authenticatable::class);
        $queryBuilderMock = Mockery::mock();

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

    /**
     * @test
     */
    public function validate_credentials()
    {
        $userMock = Mockery::mock(Authenticatable::class);
        $credentials = ['password' => 'secret'];

        $this->cerberus->shouldReceive('auth->user->checkPassword')
            ->with($credentials)
            ->andReturn(true);

        $result = $this->userProvider->validateCredentials($userMock, $credentials);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function rehash_password_if_required()
    {
        $userMock = Mockery::mock(Authenticatable::class);
        $credentials = ['password' => 'secret'];
        $force = false;

        $this->cerberus->shouldReceive('auth->user->rehashPasswordIfRequired')
            ->with($credentials, $force)
            ->andReturn(true);

        $result = $this->userProvider->rehashPasswordIfRequired($userMock, $credentials, $force);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function get_connection()
    {
        $result = $this->userProvider->getConnection();

        $this->assertSame($this->cerberus, $result);
    }
}
