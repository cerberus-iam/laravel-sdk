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
 *
 * @extends \Illuminate\Support\Fluent<string, mixed>
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
     * @return CerberusUser The Cerberus user instance.
     */
    public static function fromProfile(array $payload): CerberusUser
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
     *
     * @return string The name of the identifier field
     */
    public function getAuthIdentifierName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed The user's unique identifier
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->get('id');
    }

    /**
     * Get the password for the user.
     *
     * OAuth users don't have passwords stored locally.
     *
     * @return string|null Always returns null for OAuth users
     */
    public function getAuthPassword(): ?string
    {
        return null;
    }

    /**
     * Determine if the user has a specific role.
     *
     * @param  string  $role  The role name to check
     * @return bool True if the user has the role, false otherwise
     */
    public function hasRole(string $role): bool
    {
        $roles = $this->attributes['roles'] ?? [];

        return in_array($role, array_column($roles, 'name'), true);
    }

    /**
     * Determine if the user has a specific permission.
     *
     * @param  string  $permission  The permission name to check
     * @return bool True if the user has the permission, false otherwise
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->attributes['permissions'] ?? [];

        return in_array($permission, array_column($permissions, 'name'), true);
    }

    /**
     * Get the user's organisation.
     *
     * @return array<string, mixed>|null The organisation data or null if not available
     */
    public function organisation(): ?array
    {
        return $this->attributes['organisation'] ?? null;
    }
}
