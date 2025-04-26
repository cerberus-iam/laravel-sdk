<?php

namespace Cerberus\Resources;

class Team extends Resource
{
    /**
     * Name of the resource.
     */
    public string $resource = 'teams';

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected array $fillable = [
        'uid',
        'name',
        'slug',
        'description',
        'user_id',
        'organisation_id',
    ];
}
