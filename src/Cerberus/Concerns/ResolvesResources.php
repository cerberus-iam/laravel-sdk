<?php

namespace Cerberus\Concerns;

use BadMethodCallException;
use Cerberus\Resources\Auth;
use Cerberus\Resources\Client;
use Cerberus\Resources\Invitation;
use Cerberus\Resources\Organisation;
use Cerberus\Resources\Permission;
use Cerberus\Resources\Role;
use Cerberus\Resources\Team;
use Cerberus\Resources\TeamMember;
use Cerberus\Resources\User;
use Illuminate\Container\Container;

trait ResolvesResources
{
    /**
     * The default resources that can be resolved.
     *
     * @var array<string, string>
     */
    protected static array $defaultResources = [
        'auth' => Auth::class,
        'users' => User::class,
        'teams' => Team::class,
        'roles' => Role::class,
        'permissions' => Permission::class,
        'organisations' => Organisation::class,
        'invitations' => Invitation::class,
        'members' => TeamMember::class,
        'clients' => Client::class,
    ];

    /**
     * The custom resources overrides.
     *
     * @var array<string, string>
     */
    protected static array $resourceOverrides = [];

    /**
     * Get all resources with overrides applied.
     *
     * @return array<string, string>
     */
    protected function getResources(): array
    {
        return array_merge(self::$defaultResources, self::$resourceOverrides);
    }

    /**
     * Override a specific resource.
     */
    public static function useResource(string $key, string $resourceClass): void
    {
        self::$resourceOverrides[$key] = $resourceClass;
    }

    /**
     * Override multiple resources at once.
     *
     * @param  array<string, string>  $resources
     */
    public static function useResources(array $resources): void
    {
        foreach ($resources as $key => $resourceClass) {
            self::useResource($key, $resourceClass);
        }
    }

    /**
     * Use the specified model for user authentication.
     */
    public static function useUserModel(string $userModel): void
    {
        self::useResource('users', $userModel);
    }

    /**
     * Dynamically resolve a resource.
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $args): mixed
    {
        $resources = $this->getResources();

        if (! isset($resources[$method])) {
            throw new BadMethodCallException("Resource [{$method}] does not exist.");
        }

        $container = Container::getInstance();
        $resourceClass = $resources[$method];

        $resourceInstance = $container->bound($resourceClass)
            ? $container->make($resourceClass)
            : tap(
                new $resourceClass(...$args),
                fn ($instance) => $container->instance($resourceClass, $instance)
            );

        $https = $this->getHttpClient();

        if (! is_null($this->clientIdOverride) && ! is_null($this->clientSecretOverride)) {
            $https->withHeader(static::API_KEY_NAME, $this->clientIdOverride);
            $https->withHeader(static::API_SECRET_NAME, $this->clientSecretOverride);
        }

        $resourceInstance->setConnection($https);

        return $resourceInstance;
    }
}
