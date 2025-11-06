<?php

declare(strict_types=1);

namespace CerberusIAM\Auth;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Support\Fluent;

/**
 * Cerberus User
 *
 * This class represents a user authenticated via Cerberus IAM.
 * It's a stateless value object that extends Laravel's Fluent class
 * and uses the same traits as Laravel's default User model.
 */
class CerberusUser extends Fluent implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable;

    /**
     * The primary key for the user.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Create a Cerberus user from a profile payload.
     *
     * This method maps the profile data from Cerberus to user attributes.
     *
     * @param  array<string, mixed>  $payload  The profile data from Cerberus.
     * @return self The Cerberus user instance.
     */
    public static function fromProfile(array $payload): self
    {
        $organisation = $payload['organisation'] ?? [];

        return new self([
            'id' => $payload['id'] ?? $payload['sub'] ?? null,
            'email' => $payload['email'] ?? null,
            'name' => $payload['name'] ?? trim(($payload['firstName'] ?? '').' '.($payload['lastName'] ?? '')),
            'first_name' => $payload['firstName'] ?? null,
            'last_name' => $payload['lastName'] ?? null,
            'organisation' => [
                'id' => $organisation['id'] ?? null,
                'slug' => $organisation['slug'] ?? null,
                'name' => $organisation['name'] ?? null,
            ],
            'roles' => $payload['roles'] ?? [],
            'permissions' => $payload['permissions'] ?? [],
            'raw' => $payload,
        ]);
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->get('id');
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): ?string
    {
        return null;
    }

    /**
     * Determine if the user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        $roles = $this->attributes['roles'] ?? [];

        return in_array($role, array_column($roles, 'name'), true);
    }

    /**
     * Determine if the user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->attributes['permissions'] ?? [];

        return in_array($permission, array_column($permissions, 'name'), true);
    }

    /**
     * Get the user's organisation.
     */
    public function organisation(): ?array
    {
        return $this->attributes['organisation'] ?? null;
    }
}
