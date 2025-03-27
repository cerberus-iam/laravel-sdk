<?php

namespace Cerberus;

use Cerberus\Resources\Invitation;
use Cerberus\Resources\Organisation;
use Cerberus\Resources\Permission;
use Cerberus\Resources\Role;
use Cerberus\Resources\Team;
use Cerberus\Resources\TeamMember;
use Cerberus\Resources\User;
use Fetch\Interfaces\ClientHandler;

class Cerberus
{
    /**
     * The base URI for the Cerberus API.
     *
     * @var string
     */
    public const API_URI = 'https://api.cerberus-iam.com';

    /**
     * The API version.
     *
     * @var string
     */
    public const API_VERSION = 'v1';

    /**
     * The name of the API key header.
     *
     * @var string
     */
    public const API_KEY_NAME = 'X-Cerberus-Client-Id';

    /**
     * The name of the API secret header.
     *
     * @var string
     */
    public const API_SECRET_NAME = 'X-Cerberus-Client-Secret';

    /**
     * The resources available in the Cerberus API.
     *
     * @var array<string, class-string>
     */
    protected static $resources = [
        'users' => User::class,
        'teams' => Team::class,
        'roles' => Role::class,
        'permissions' => Permission::class,
        'organisations' => Organisation::class,
        'invitations' => Invitation::class,
        'members' => TeamMember::class,
    ];

    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct(protected ClientHandler $http)
    {
        //
    }

    /**
     * Get the resource class for the given resource name.
     *
     * @return class-string
     */
    public function getResourceClass(string $resource): string
    {
        if (! array_key_exists($resource, self::$resources)) {
            throw new \InvalidArgumentException("Resource [{$resource}] not found.");
        }

        return self::$resources[$resource];
    }

    /**
     * Get the HTTP client handler.
     */
    public function getHttpClient(): ClientHandler
    {
        return $this->http;
    }

    /**
     * Dynamically call the resource classes.
     *
     * @param  string  $method
     * @param  array  $args
     * @return mixed
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $args)
    {
        if (array_key_exists($method, self::$resources)) {
            $resource = $this->getResourceClass($method);

            return new $resource($this->http);
        }

        throw new \BadMethodCallException("Method [{$method}] does not exist.");
    }
}
