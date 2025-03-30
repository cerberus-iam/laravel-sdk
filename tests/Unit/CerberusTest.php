<?php

namespace Cerberus\Tests\Unit;

use Cerberus\Cerberus;
use Cerberus\Resources\Auth;
use Cerberus\Resources\User;
use Fetch\Interfaces\ClientHandler;
use Illuminate\Container\Container;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Orchestra\Testbench\TestCase;
use RuntimeException;

class CerberusTest extends TestCase
{
    use WithFaker;

    protected $http;

    protected $cerberus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = Mockery::mock(ClientHandler::class);

        // Set up the default behavior for the HTTP client
        $this->http->shouldReceive('hasHeader')->with('Authorization')->andReturn(true)->byDefault();
        $this->http->shouldReceive('withToken')->andReturnSelf()->byDefault();

        $this->cerberus = new Cerberus($this->http);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Setup default configuration
        $app['config']->set('services.cerberus.key', 'test-client-id');
        $app['config']->set('services.cerberus.secret', 'test-client-secret');
    }

    public function test_get_http_client()
    {
        $this->assertSame($this->http, $this->cerberus->getHttpClient());
    }

    public function test_testing_mode()
    {
        $this->http->shouldReceive('withHeaders')
            ->once()
            ->with([Cerberus::API_TESTING_MODE => 'true'])
            ->andReturnSelf();

        $result = $this->cerberus->testing();

        $this->assertSame($this->cerberus, $result);
    }

    public function test_configure_access_token_when_authorization_header_is_already_set()
    {
        $this->http->shouldReceive('hasHeader')
            ->once()
            ->with('Authorization')
            ->andReturn(true);

        // Should not attempt to get access token
        $this->http->shouldNotReceive('withToken');

        $result = $this->cerberus->configureAccessToken();

        $this->assertSame($this->cerberus, $result);
    }

    public function test_configure_access_token_when_authorization_header_is_not_set()
    {
        $accessToken = $this->faker->uuid;

        // Create a new HTTP mock specifically for this test
        $httpMock = Mockery::mock(ClientHandler::class);

        // Set up expectations for HTTP mock
        $httpMock->shouldReceive('hasHeader')
            ->with('Authorization')
            ->andReturn(false);

        $httpMock->shouldReceive('withToken')
            ->once()
            ->with($accessToken)
            ->andReturnSelf();

        // Mock cache to provide a token
        Cache::shouldReceive('get')
            ->once()
            ->with(Cerberus::CACHE_KEY_TOKEN)
            ->andReturn([
                'access_token' => $accessToken,
                'expires_in' => 3600,
            ]);

        // Create a new instance with our mocks to avoid the constructor call
        $cerberus = new class($httpMock) extends Cerberus
        {
            // Override constructor to prevent calling configureAccessToken
            public function __construct($http)
            {
                $this->http = $http;
                // Not calling parent::__construct to avoid configureAccessToken call
            }
        };

        $result = $cerberus->configureAccessToken();

        $this->assertSame($cerberus, $result);
    }

    public function test_get_access_token_from_cache()
    {
        $accessToken = $this->faker->uuid;
        $expiresIn = 3600;

        Cache::shouldReceive('get')
            ->once()
            ->with(Cerberus::CACHE_KEY_TOKEN)
            ->andReturn([
                'access_token' => $accessToken,
                'expires_in' => $expiresIn,
            ]);

        // Create a test subclass that exposes the protected method
        $cerberus = new class($this->http) extends Cerberus
        {
            public function getAccessTokenPublic(): array
            {
                return $this->getAccessToken();
            }
        };

        $result = $cerberus->getAccessTokenPublic();

        $this->assertEquals([
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
        ], $result);
    }

    public function test_get_access_token_from_api()
    {
        $accessToken = $this->faker->uuid;
        $expiresIn = 3600;

        // Mock Cache to return no cached token
        Cache::shouldReceive('get')
            ->once()
            ->with(Cerberus::CACHE_KEY_TOKEN)
            ->andReturn(null);

        // Mock HTTP response
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('ok')
            ->once()
            ->andReturn(true);

        $response->shouldReceive('json')
            ->once()
            ->andReturn([
                'access_token' => $accessToken,
                'expires_in' => $expiresIn,
            ]);

        $this->http->shouldReceive('post')
            ->once()
            ->with('/oauth/token', [
                'grant_type' => Cerberus::GRANT_TYPE,
                'client_id' => config('services.cerberus.key'),
                'client_secret' => config('services.cerberus.secret'),
                'scope' => '*',
            ])
            ->andReturn($response);

        // Mock Cache::put with any third argument instead of specific type
        Cache::shouldReceive('put')
            ->once()
            ->with(
                Cerberus::CACHE_KEY_TOKEN,
                [
                    'access_token' => $accessToken,
                    'expires_in' => $expiresIn,
                ],
                Mockery::any()  // Accept any value for the TTL parameter
            );

        // Create a test subclass that exposes the protected method
        $cerberus = new class($this->http) extends Cerberus
        {
            public function getAccessTokenPublic(): array
            {
                return $this->getAccessToken();
            }

            // Override constructor to prevent calling configureAccessToken
            public function __construct($http)
            {
                $this->http = $http;
                // Not calling parent::__construct to avoid configureAccessToken call
            }
        };

        $result = $cerberus->getAccessTokenPublic();

        $this->assertEquals([
            'access_token' => $accessToken,
            'expires_in' => $expiresIn,
        ], $result);
    }

    public function test_get_access_token_from_api_fails_with_bad_response()
    {
        // Mock Cache to return no cached token
        Cache::shouldReceive('get')
            ->once()
            ->with(Cerberus::CACHE_KEY_TOKEN)
            ->andReturn(null);

        // Mock HTTP response
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('ok')
            ->once()
            ->andReturn(false);

        $this->http->shouldReceive('post')
            ->once()
            ->with('/oauth/token', Mockery::type('array'))
            ->andReturn($response);

        // Create a test subclass that exposes the protected method
        $cerberus = new class($this->http) extends Cerberus
        {
            public function getAccessTokenPublic(): array
            {
                return $this->getAccessToken();
            }

            // Override constructor to prevent calling configureAccessToken
            public function __construct($http)
            {
                $this->http = $http;
                // Not calling parent::__construct to avoid configureAccessToken call
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch Cerberus client access token.');

        $cerberus->getAccessTokenPublic();
    }

    public function test_get_access_token_from_api_fails_with_incomplete_response()
    {
        // Mock Cache to return no cached token
        Cache::shouldReceive('get')
            ->once()
            ->with(Cerberus::CACHE_KEY_TOKEN)
            ->andReturn(null);

        // Mock HTTP response
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('ok')
            ->once()
            ->andReturn(true);

        $response->shouldReceive('json')
            ->once()
            ->andReturn([
                // Missing required fields
                'token_type' => 'Bearer',
            ]);

        $this->http->shouldReceive('post')
            ->once()
            ->with('/oauth/token', Mockery::type('array'))
            ->andReturn($response);

        // Create a test subclass that exposes the protected method
        $cerberus = new class($this->http) extends Cerberus
        {
            public function getAccessTokenPublic(): array
            {
                return $this->getAccessToken();
            }

            // Override constructor to prevent calling configureAccessToken
            public function __construct($http)
            {
                $this->http = $http;
                // Not calling parent::__construct to avoid configureAccessToken call
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid access token response from Cerberus.');

        $cerberus->getAccessTokenPublic();
    }

    public function test_magic_call_with_existing_resource()
    {
        $resourceName = 'users';
        $resourceClass = User::class;

        // Swap Container for testing
        $originalContainer = Container::getInstance();
        $container = new Container;
        Container::setInstance($container);

        // Call the method
        $result = $this->cerberus->users();

        // Verify result
        $this->assertInstanceOf(User::class, $result);

        // Reset container
        Container::setInstance($originalContainer);
    }

    public function test_magic_call_with_cached_resource()
    {
        $resourceName = 'auth';
        $resourceClass = Auth::class;

        // Create a resource instance to return
        $resourceInstance = new Auth($this->http);

        // Swap Container for testing
        $originalContainer = Container::getInstance();
        $container = new Container;
        $container->instance($resourceClass, $resourceInstance);
        Container::setInstance($container);

        // Call the method
        $result = $this->cerberus->auth();

        // Verify result
        $this->assertSame($resourceInstance, $result);

        // Reset container
        Container::setInstance($originalContainer);
    }

    public function test_magic_call_with_non_existent_resource()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Resource [nonexistent] does not exist.');

        $this->cerberus->nonexistent();
    }
}
