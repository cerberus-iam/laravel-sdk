<?php

namespace Cerberus\Tests\Stubs;

use Cerberus\Resources\Resource;

/**
 * Test implementation of the abstract Resource class
 */
class TestResource extends Resource
{
    public string $resource = 'test/resources';

    protected string $primaryKey = 'id';

    protected array $fillable = ['id', 'name', 'email', 'status'];

    protected array $hidden = ['secret_field'];
}
