<?php

namespace Cerberus\Concerns;

use BadMethodCallException;
use Cerberus\Resources\Auth;
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
     * The resources that can be resolved.
     *
     * @var array<string, string>
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
     * Dynamically resolve a resource.
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $args): mixed
    {
        if (! isset(self::$resources[$method])) {
            throw new BadMethodCallException("Resource [{$method}] does not exist.");
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
