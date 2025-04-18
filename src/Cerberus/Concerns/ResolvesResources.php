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
     * Default resource mappings
     *
     * @var array<string, class-string>
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
     * Overridden resource mappings
     *
     * @var array<string, string>
     */
    protected static array $resourceOverrides = [];

    /**
     * Get merged list of resources.
     *
     * @return array<string, string>
     */
    protected function getResources(): array
    {
        return array_merge(self::$defaultResources, self::$resourceOverrides);
    }

    /**
     * Override a single resource mapping.
     */
    public static function useResource(string $key, string $class): void
    {
        self::$resourceOverrides[$key] = $class;
    }

    /**
     * Override multiple resource mappings.
     *
     * @param  array<string, string>  $resources
     */
    public static function useResources(array $resources): void
    {
        foreach ($resources as $key => $class) {
            self::useResource($key, $class);
        }
    }

    /**
     * Set custom user resource model.
     */
    public static function useUserModel(string $model): void
    {
        self::useResource('users', $model);
    }

    /**
     * Magic method to resolve resource calls dynamically.
     *
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $args)
    {
        $resources = $this->getResources();

        if (! isset($resources[$method])) {
            throw new BadMethodCallException("Resource [{$method}] does not exist.");
        }

        $container = Container::getInstance();
        $class = $resources[$method];
        $instance = $container->bound($class)
            ? $container->make($class)
            : tap(new $class(...$args), fn ($i) => $container->instance($class, $i));

        $http = $this->getHttpClient();

        $this->applyClientOverrides($http);

        $this->applyImpersonation($http);

        $instance->setConnection($http);

        return $instance;
    }
}
