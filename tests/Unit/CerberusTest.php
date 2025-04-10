<?php

namespace Cerberus\Tests\Unit;

use Cerberus\Cerberus;
use Cerberus\Contracts\TokenStorage;
use Cerberus\Resources\Auth;
use Cerberus\Resources\User;
use Cerberus\Tests\TestCase;
use Cerberus\Token;
use Fetch\Http\Response;
use Fetch\Interfaces\ClientHandler;
use Illuminate\Container\Container;
use Mockery;

class CerberusTest extends TestCase
{
    protected $http;

    protected $cerberus;

    protected $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = Mockery::mock(ClientHandler::class);

        app()->bind(ClientHandler::class, function () {
            return $this->http;
        });

        $this->storage = Mockery::mock(TokenStorage::class);

        $this->http->shouldReceive('hasHeader')->with('Authorization')->andReturn(true)->byDefault();
        $this->http->shouldReceive('withToken')->andReturnSelf()->byDefault();

        $this->cerberus = new class($this->http) extends Cerberus
        {
            public function __construct($http)
            {
                $this->http = $http;
            }

            protected function parseAccessToken(string $token): Token
            {
                $mock = Mockery::mock(Token::class);
                $mock->shouldReceive('getTokenId')->andReturn('mocked-token-id');
                $mock->shouldReceive('getUserId')->andReturn('mocked-user-id');
                $mock->shouldReceive('getClientId')->andReturn('mocked-client-id');
                $mock->shouldReceive('isExpired')->andReturn(false);

                return $mock;
            }
        };

        $this->cerberus->setTokenStorage($this->storage);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();

        Mockery::close();
        parent::tearDown();
    }

    public function test_can_get_http_client(): void
    {
        $this->assertSame($this->http, $this->cerberus->getHttpClient());
    }

    public function test_should_enable_testing_mode(): void
    {
        $this->http->shouldReceive('withHeader')
            ->once()
            ->with(Cerberus::API_TESTING_MODE, true)
            ->andReturnSelf();

        $result = $this->cerberus->testing();

        $this->assertSame($this->cerberus, $result);
    }

    public function test_should_configure_access_token_when_missing(): void
    {
        $token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.'
               .'eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvZSIsImlhdCI6MTUxNjIzOTAyMn0.'
               .'SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
        $expires = 3600;

        $this->http->shouldReceive('hasHeader')->andReturn(false);
        $this->http->shouldReceive('withToken')->once()->with($token)->andReturnSelf();
        $this->http->shouldReceive('post')->andReturn(
            Mockery::mock(
                Response::class,
                fn ($m) => $m->shouldReceive('json')->andReturn([
                    'access_token' => $token,
                    'expires_in' => $expires,
                ])
            )
        );

        $this->storage->shouldReceive('get')->andReturn(null);
        $this->storage->shouldReceive('put')->with([
            'access_token' => $token,
            'expires_in' => $expires,
        ], $expires);

        $result = $this->cerberus->configureAccessToken();
        $this->assertSame($this->cerberus, $result);
    }

    public function test_should_return_user_resource_instance(): void
    {
        $original = Container::getInstance();
        $container = new Container;

        // Pre-bind User so it's returned correctly without extra constructor
        $container->bind(User::class, function () {
            $user = new User;
            $user->setConnection($this->http);

            return $user;
        });

        Container::setInstance($container);

        $user = $this->cerberus->users();

        $this->assertInstanceOf(User::class, $user);

        Container::setInstance($original);
    }

    public function test_should_return_cached_resource_instance(): void
    {
        $auth = new Auth; // Pass no arguments
        $auth->setConnection($this->http); // Inject HTTP client

        $container = new Container;
        $container->instance(Auth::class, $auth);

        $original = Container::getInstance();
        Container::setInstance($container);

        $resource = $this->cerberus->auth();
        $this->assertSame($auth, $resource);

        Container::setInstance($original);
    }

    public function test_should_throw_exception_for_invalid_resource(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Resource [nonexistent] does not exist.');

        $this->cerberus->nonexistent();
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('services.cerberus.key', 'test-key');
        $app['config']->set('services.cerberus.secret', 'test-secret');
    }
}
