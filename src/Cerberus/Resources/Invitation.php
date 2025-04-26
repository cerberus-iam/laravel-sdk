<?php

namespace Cerberus\Resources;

class Invitation extends Resource
{
    /**
     * Name of the resource.
     */
    public string $resource = 'invitations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected array $fillable = [
        'email',
        'role',
        'organisation_id',
    ];
}
