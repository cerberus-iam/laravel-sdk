<?php

namespace Cerberus\Tests\Unit;

use Cerberus\Resources\Auth;
use Cerberus\Resources\User;
use Fetch\Interfaces\ClientHandler as ClientHandlerInterface;
use Mockery;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    protected ClientHandlerInterface $connection;

    protected Auth $auth;

    protected function setUp(): void
    {
        parent::setUp();

        // Tell Mockery to mock the required interface
        $this->connection = Mockery::mock(ClientHandlerInterface::class);
        $this->auth = new Auth($this->connection);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_user_can_be_set_and_retrieved()
    {
        $user = Mockery::mock(User::class);

        $result = $this->auth->user($user);

        // Assert that method returns the same Auth instance
        $this->assertSame($this->auth, $result);

        // Use Reflection to access protected property for assertion (if you *really* want to)
        $reflection = new \ReflectionClass($this->auth);
        $property = $reflection->getProperty('user');
        $property->setAccessible(true);
        $storedUser = $property->getValue($this->auth);

        $this->assertSame($user, $storedUser);
    }

    public function test_authenticate_via_credentials_returns_token_response()
    {
        $credentials = ['email' => 'test@example.com', 'password' => 'secret'];
        $expected = ['access_token' => 'fake-token'];

        $this->connection->shouldReceive('post')
            ->with('/login', $credentials)
            ->once()
            ->andReturn(
                Mockery::mock(['json' => $expected])
            );

        $result = $this->auth->authenticateViaCredentials($credentials);

        $this->assertEquals($expected, $result);
    }

    public function test_find_by_token_returns_user_when_successful()
    {
        $userData = ['id' => 1, 'email' => 'test@example.com'];
        $responseMock = Mockery::mock([
            'ok' => true,
            'json' => $userData,
        ]);

        $this->connection->shouldReceive('get')
            ->with('/user')
            ->once()
            ->andReturn($responseMock);

        $result = $this->auth->findByToken();

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($userData['email'], $result->email);
    }

    public function test_find_by_token_returns_null_when_response_is_not_ok()
    {
        $this->connection->shouldReceive('get')
            ->with('/user')
            ->once()
            ->andReturn(Mockery::mock(['ok' => false]));

        $result = $this->auth->findByToken();

        $this->assertNull($result);
    }

    public function test_check_password_sends_correct_payload_with_user_set()
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('getAuthPasswordName')->andReturn('password');

        $this->auth->user($user);

        $credentials = ['email' => 'test@example.com', 'password' => 'secret'];

        $this->connection->shouldReceive('post')
            ->with('/check-password', [
                'email' => 'test@example.com',
                'password' => 'secret',
            ])
            ->once()
            ->andReturn(Mockery::mock(['ok' => true]));

        $this->assertTrue($this->auth->checkPassword($credentials));
    }

    public function test_rehash_password_if_required_sends_correct_payload()
    {
        $credentials = ['email' => 'test@example.com', 'password' => 'newpassword'];

        $this->connection->shouldReceive('withQueryParameters')
            ->with(['email' => $credentials['email']])
            ->andReturnSelf();

        $this->connection->shouldReceive('post')
            ->with('/rehash-password', [
                'password' => 'newpassword',
                'force' => false,
            ])
            ->once();

        $this->auth->rehashPasswordIfRequired($credentials);
        $this->assertTrue(true); // Just verifying no exception
    }

    public function test_reset_password_sends_correct_payload()
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
        $this->assertTrue(true); // No exception
    }
}
