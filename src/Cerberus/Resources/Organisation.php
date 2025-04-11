<?php

namespace Cerberus\Resources;

use Exception;

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
     * Onboard a new organisation.
     *
     * @param  array<int, string>  $data
     * @return array<int, mixed>
     */
    public static function onboard(array $data): array
    {
        $result = (new self)->getConnection()
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
            ->json();

        if (! $result->ok()) {
            throw new Exception('Error onboarding organisation: '.$result['message']);
        }

        return $result;
    }
}
