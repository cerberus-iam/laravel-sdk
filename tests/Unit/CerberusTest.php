<?php

namespace Cerberus\Tests\Unit;

use BadMethodCallException;
use Cerberus\Cerberus;
use Cerberus\Contracts\TokenStorage;
use Cerberus\Resources\Auth;
use Cerberus\Resources\User;
use Cerberus\Token;
use Fetch\Http\Response;
use Fetch\Interfaces\ClientHandler;
use Illuminate\Container\Container;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery;
use Orchestra\Testbench\TestCase;
use RuntimeException;

class CerberusTest extends TestCase
{
    use WithFaker;

    protected $http;

    protected $cerberus;

    protected $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(ClientHandler::class, function () {
            $mock = Mockery::mock(ClientHandler::class);

            // These are safe defaults for any internal token resource interaction
            $mock->shouldReceive('post')->zeroOrMoreTimes()->andReturn(
                Mockery::mock(Response::class, function ($m) {
                    $m->shouldReceive('json')->andReturn([]);
                })
            );
            $mock->shouldReceive('get')->zeroOrMoreTimes()->andReturn(
                Mockery::mock(Response::class, function ($m) {
                    $m->shouldReceive('json')->andReturn([]);
                })
            );
            $mock->shouldReceive('withQueryParameters')->zeroOrMoreTimes()->andReturnSelf();

            return $mock;
        });

        $this->http = Mockery::mock(ClientHandler::class);
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
            ->with(Cerberus::API_TESTING_MODE, 'true')
            ->andReturnSelf();

        $result = $this->cerberus->testing();

        $this->assertSame($this->cerberus, $result);
    }

    public function test_should_not_configure_access_token_when_authorization_header_is_already_set(): void
    {
        $this->http->shouldReceive('hasHeader')
            ->once()
            ->with('Authorization')
            ->andReturn(true);

        $this->http->shouldNotReceive('withToken');

        $result = $this->cerberus->configureAccessToken();

        $this->assertSame($this->cerberus, $result);
    }

    public function test_should_configure_access_token_when_authorization_header_is_not_set(): void
    {
        $accessToken = $this->validJwt();

        $http = Mockery::mock(ClientHandler::class);
        $storage = Mockery::mock(TokenStorage::class);

        $http->shouldReceive('hasHeader')->with('Authorization')->andReturn(false);
        $http->shouldReceive('withToken')->with($accessToken)->once()->andReturnSelf();

        $http->shouldReceive('post')->zeroOrMoreTimes()->andReturn(
            Mockery::mock(Response::class, fn ($m) => $m->shouldReceive('json')->andReturn([
                'access_token' => $accessToken,
                'expires_in' => 3600,
            ]))
        );

        $storage->shouldReceive('get')->once()->andReturn([
            'access_token' => $accessToken,
            'expires_in' => 3600,
        ]);
        $storage->shouldReceive('put')
            ->once()
            ->with([
                'access_token' => $accessToken,
                'expires_in' => 3600,
            ], 3600);

        $cerberus = new class($http) extends Cerberus
        {
            public function __construct($http)
            {
                $this->http = $http;
            }

            protected function parseAccessToken(string $token): Token
            {
                $mock = Mockery::mock(Token::class);
                $mock->shouldReceive('getTokenId')->andReturn('token-id');
                $mock->shouldReceive('getUserId')->andReturn('user-id');
                $mock->shouldReceive('getClientId')->andReturn('client-id');
                $mock->shouldReceive('isExpired')->andReturn(false);

                return $mock;
            }
        };

        $cerberus->setTokenStorage($storage);

        $result = $cerberus->configureAccessToken();

        $this->assertSame($cerberus, $result);
    }

    public function test_should_get_access_token_from_cache(): void
    {
        $accessToken = $this->validJwt();
        $expiresIn = 3600;

        $this->http->shouldReceive('post')->zeroOrMoreTimes()->andReturn(
            Mockery::mock(Response::class, fn ($m) => $m->shouldReceive('json')->andReturn([
                'access_token' => $accessToken,
                'expires_in' => $expiresIn,
            ]))
        );

        $this->storage->shouldReceive('get')
            ->once()
            ->andReturn([
                'access_token' => $accessToken,
                'expires_in' => $expiresIn,
            ]);
        $this->storage->shouldReceive('put')->once()->with([
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
        ], $expiresIn);

        $result = $this->cerberus->getAccessToken();

        $this->assertEquals([
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
        ], $result);
    }

    public function test_should_get_access_token_from_api(): void
    {
        $accessToken = $this->validJwt();
        $expiresIn = 3600;

        $this->storage->shouldReceive('get')->once()->andReturn(null);
        $this->storage->shouldReceive('put')->once()->with(
            [
                'access_token' => $accessToken,
                'expires_in' => $expiresIn,
            ],
            $expiresIn
        );

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('json')->once()->andReturn([
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
        ]);

        $this->http->shouldReceive('post')->once()->with('/oauth/token', [
            'grant_type' => Cerberus::GRANT_TYPE,
            'client_id' => config('services.cerberus.key'),
            'client_secret' => config('services.cerberus.secret'),
            'scope' => '*',
        ])->andReturn($response);

        $result = $this->cerberus->getAccessToken();

        $this->assertEquals([
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
        ], $result);
    }

    public function test_should_throw_exception_when_access_token_api_response_is_not_ok(): void
    {
        $this->storage->shouldReceive('get')->once()->andReturn(null);

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('json')->once()->andReturn([
            'error' => 'invalid_client',
        ]);

        $this->http->shouldReceive('post')->once()->andReturn($response);

        $cerberus = $this->cerberus;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid access token response from Cerberus.');

        $cerberus->getAccessToken();
    }

    public function test_should_throw_exception_when_access_token_api_response_is_incomplete(): void
    {
        $this->storage->shouldReceive('get')->once()->andReturn(null);

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('json')->once()->andReturn([
            'token_type' => 'Bearer',
        ]);

        $this->http->shouldReceive('post')->once()->andReturn($response);

        $cerberus = $this->cerberus;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid access token response from Cerberus.');

        $cerberus->getAccessToken();
    }

    public function test_should_resolve_existing_resource_via_magic_call(): void
    {
        $originalContainer = Container::getInstance();
        $container = new Container;
        Container::setInstance($container);

        $result = $this->cerberus->users();

        $this->assertInstanceOf(User::class, $result);

        Container::setInstance($originalContainer);
    }

    public function test_should_return_cached_resource_via_magic_call(): void
    {
        $resourceInstance = new Auth($this->http);

        $originalContainer = Container::getInstance();
        $container = new Container;
        $container->instance(Auth::class, $resourceInstance);
        Container::setInstance($container);

        $result = $this->cerberus->auth();

        $this->assertSame($resourceInstance, $result);

        Container::setInstance($originalContainer);
    }

    public function test_should_throw_exception_for_non_existent_resource(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Resource [nonexistent] does not exist.');

        $this->cerberus->nonexistent();
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('services.cerberus.key', 'test-client-id');
        $app['config']->set('services.cerberus.secret', 'test-client-secret');
    }

    protected function validJwt(): string
    {
        return 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.'
             .'eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvZSIsImlhdCI6MTUxNjIzOTAyMn0.'
             .'SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
    }
}
