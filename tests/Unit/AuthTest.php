<?php

namespace Cerberus\Tests\Unit;

use Cerberus\Resources\Auth;
use Cerberus\Resources\User;
use Fetch\Interfaces\ClientHandler;
use Mockery;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    protected $connection;

    protected $auth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = Mockery::mock(ClientHandler::class);
        $this->auth = new Auth;
        $this->auth->setConnection($this->connection);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_user_can_be_set_and_retrieved(): void
    {
        $user = Mockery::mock(User::class);

        $this->auth->user($user);

        $this->assertSame($this->auth, $this->auth->user($user)); // chaining
    }

    public function test_authenticate_via_credentials_returns_token_response(): void
    {
        $credentials = ['email' => 'test@example.com', 'password' => 'secret'];
        $expected = ['access_token' => 'fake-token'];

        $this->connection->shouldReceive('post')
            ->with('/login', $credentials)
            ->once()
            ->andReturn(Mockery::mock(['json' => $expected]));

        $result = $this->auth->authenticateViaCredentials($credentials);

        $this->assertEquals($expected, $result);
    }

    public function test_find_by_token_returns_user_when_successful(): void
    {
        $userData = ['id' => 1, 'email' => 'test@example.com'];

        $this->connection->shouldReceive('get')
            ->with('/user')
            ->once()
            ->andReturn(Mockery::mock([
                'ok' => true,
                'json' => $userData,
            ]));

        $result = $this->auth->findByToken();

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($userData['email'], $result->email);
    }

    public function test_find_by_token_returns_null_when_response_is_not_ok(): void
    {
        $this->connection->shouldReceive('get')
            ->with('/user')
            ->once()
            ->andReturn(Mockery::mock(['ok' => false]));

        $this->assertNull($this->auth->findByToken());
    }

    public function test_check_password_sends_correct_payload_with_user_set(): void
    {
        $credentials = ['email' => 'test@example.com', 'password' => 'secret'];
        $user = Mockery::mock(User::class);
        $user->shouldReceive('getAuthPasswordName')->andReturn('password');

        $this->auth->user($user);

        $this->connection->shouldReceive('post')
            ->with('/check-password', [
                'email' => 'test@example.com',
                'password' => 'secret',
            ])
            ->once()
            ->andReturn(Mockery::mock(['ok' => true]));

        $this->assertTrue($this->auth->checkPassword($credentials));
    }

    public function test_rehash_password_if_required_sends_correct_payload(): void
    {
        $credentials = ['email' => 'test@example.com', 'password' => 'newpassword'];

        $this->connection->shouldReceive('withQueryParameters')
            ->with(['email' => $credentials['email']])
            ->once()
            ->andReturnSelf();

        $this->connection->shouldReceive('post')
            ->with('/rehash-password', [
                'password' => 'newpassword',
                'force' => false,
            ])
            ->once();

        $this->auth->rehashPasswordIfRequired($credentials);

        $this->assertTrue(true); // basic smoke check
    }

    public function test_reset_password_sends_correct_payload(): void
    {
        $email = 'test@example.com';
        $password = 'newpassword';

        $this->connection->shouldReceive('post')
            ->with('/reset-password', [
                'email' => $email,
                'password' => $password,
            ])
            ->once();

        $this->auth->resetPassword($email, $password);

        $this->assertTrue(true); // no exceptions = pass
    }
}
