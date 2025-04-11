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

    /**
     * The attributes that are hidden from the response.
     *
     * @var array<int, string>
     */
    public static function onboard(array $data): bool
    {
        return self::newInstance()
            ->getConnection()
            ->post('/onboarding', [
                'organisation' => [
                    'name' => $data['organisation'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                ],
                'owner' => [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'password' => $data['password'],
                    'password_confirmation' => $data['password_confirmation'],
                ],
            ])
            ->ok();
    }
}
