<?php

namespace Cerberus\Resources;

class Permission extends Resource
{
    /**
     * Name of the resource.
     */
    public string $resource = 'permissions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected array $fillable = [
        'name',
    ];
}
