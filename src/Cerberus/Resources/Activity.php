<?php

namespace Cerberus\Resources;

class Activity extends Resource
{
    /**
     * Name of the resource.
     */
    public string $resource = 'activities';

    /**
     * The attributes that should be cast.
     *
     * @var array<int, string>
     */
    protected array $fillable = [
        'organisation_id',
        'user_id',
        'type',
        'changes',
        'trackable_type',
        'trackable_id',
        'started_at',
        'ended_at',
    ];
}
