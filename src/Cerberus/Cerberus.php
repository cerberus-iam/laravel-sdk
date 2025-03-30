<?php

namespace Cerberus;

use Cerberus\Resources\{
    Auth,
    Invitation,
    Organisation,
    Permission,
    Role,
    Team,
    TeamMember,
    User
};
use Fetch\Interfaces\ClientHandler;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class Cerberus
{
    /**
     * The base URI of the Cerberus API.
     */
    public const API_URI = 'http://127.0.0.1:8000';

    /**
     * The version of the Cerberus API.
     */
    public const API_VERSION = 'v1';

    /**
     * The Cerberus API client ID header.
     */
    public const HEADER_CLIENT_ID = 'X-Cerberus-Client-Id';

    /**
     * The Cerberus API client secret header.
     */
    public const HEADER_CLIENT_SECRET = 'X-Cerberus-Client-Secret';

    /**
     * The Cerberus API testing header.
     */
    public const HEADER_TESTING = 'X-Cerberus-Testing';

    /**
     * The Cerberus API client credentials grant type.
     */
    public const GRANT_TYPE = 'client_credentials';

    /**
     * The Cerberus API client access token cache key.
     */
    public const CACHE_KEY_TOKEN = 'cerberus.client_access_token';

    /**
     * Available API resources.
     *
     * @var array<string, class-string>
     */
    protected static array $resources = [
        'auth' => Auth::class,
        'users' => User::class,
        'teams' => Team::class,
        'roles' => Role::class,
        'permissions' => Permission::class,
        'organisations' => Organisation::class,
        'invitations' => Invitation::class,
        'members' => TeamMember::class,
    ];

    /**
     * Cerberus constructor.
     */
    public function __construct(
        protected ClientHandler $http
    ) {
        $this->configureAccessToken();
    }

    /**
     * Get the HTTP client handler.
     */
    public function getHttpClient(): ClientHandler
    {
        return $this->http;
    }

    /**
     * Enable testing mode by setting the appropriate header.
     */
    public function testing(): static
    {
        $this->http->withHeaders([
            self::HEADER_TESTING => 'true',
        ]);

        return $this;
    }

    /**
     * Configure the access token on the client, unless already set.
     */
    public function configureAccessToken(): static
    {
        if (! $this->http->hasHeader('Authorization')) {
            $this->http->withToken($this->getAccessToken()['access_token']);
        }

        return $this;
    }

    /**
     * Fetch an access token using client credentials grant.
     *
     * @return array{access_token: string, expires_in: int}
     *
     * @throws \RuntimeException
     */
    protected function getAccessToken(): array
    {
        $cached = Cache::get(self::CACHE_KEY_TOKEN);

        if (
            is_array($cached) &&
            isset($cached['access_token'], $cached['expires_in'])
        ) {
            return $cached;
        }

        $response = $this->http->post('/oauth/token', [
            'grant_type' => self::GRANT_TYPE,
            'client_id' => config('services.cerberus.key'),
            'client_secret' => config('services.cerberus.secret'),
            'scope' => '*',
        ]);

        if (! $response->ok()) {
            throw new RuntimeException('Failed to fetch Cerberus client access token.');
        }

        $data = $response->json();

        if (! isset($data['access_token'], $data['expires_in'])) {
            throw new RuntimeException('Invalid access token response from Cerberus.');
        }

        Cache::put(self::CACHE_KEY_TOKEN, $data, now()->addSeconds($data['expires_in']));

        return $data;
    }

    /**
     * Dynamically resolve a resource.
     *
     *
     * @throws \BadMethodCallException
     */
    public function __call(string $method, array $args): mixed
    {
        if (! isset(self::$resources[$method])) {
            throw new \BadMethodCallException("Resource [{$method}] does not exist.");
        }

        $container = Container::getInstance();
        $resourceClass = self::$resources[$method];

        return $container->bound($resourceClass)
            ? $container->make($resourceClass)
            : tap(
                new $resourceClass($this->http, ...$args),
                fn ($instance) => $container->instance($resourceClass, $instance)
            );
    }
}
