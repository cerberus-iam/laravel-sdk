<?php

namespace Cerberus\Resources;

class Role extends Resource
{
    /**
     * Name of the resource.
     */
    public string $resource = 'roles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected array $fillable = [
        'name',
        'slug',
        'organisation_id',
    ];
}
