<?php

namespace Cerberus\Resources;

class Organisation extends Resource
{
    /**
     * Name of the resource.
     */
    public string $resource = 'organisations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected array $fillable = [
        'id',
        'uid',
        'email',
        'name',
        'slug',
        'phone',
        'website',
        'logo',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
}
