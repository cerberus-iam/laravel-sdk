<?php

namespace Cerberus\Resources;

use Fetch\Interfaces\ClientHandler;

class Token extends Resource
{
    /**
     * Name of the resource.
     */
    protected string $resource = 'auth';

    /**
     * The primary key of the resource.
     */
    protected string $primaryKey = 'access_token';

    /**
     * Create new instance of the resource.
     *
     * @return void
     */
    public function __construct(ClientHandler $connection, array $attributes = [])
    {
        $attributes['expires_at'] = now()->addSeconds($attributes['expires_in']);

        parent::__construct($connection, $attributes);
    }
}
