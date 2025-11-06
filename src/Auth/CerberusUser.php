<?php

declare(strict_types=1);

namespace CerberusIAM\Auth;

use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Support\Fluent;

/**
 * Cerberus User
 *
 * This class represents a user authenticated via Cerberus IAM.
 * It's a stateless value object that extends Laravel's Fluent class.
 */
class CerberusUser extends Fluent implements
    AuthenticatableContract,
    AuthorizableContract
{
    use Authorizable;

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
     *
     * @return string The identifier name.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed The user identifier.
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->attributes['id'] ?? null;
    }

    /**
     * Get the password for the user (not applicable for OAuth).
     *
     * @return string|null Always returns null.
     */
    public function getAuthPassword(): ?string
    {
        return null;
    }

    /**
     * Get the password column name (not applicable for OAuth).
     *
     * @return string The password column name.
     */
    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    /**
     * Get the remember token (not supported).
     *
     * @return string|null Always returns null.
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    /**
     * Set the remember token (not supported).
     *
     * @param  string  $value  The token value.
     */
    public function setRememberToken($value): void
    {
        // Remember tokens are not supported for OAuth authentication
    }

    /**
     * Get the remember token column name.
     *
     * @return string The remember token column name.
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    /**
     * Get an attribute from the user.
     *
     * @param  string  $key  The attribute key.
     * @param  mixed  $default  The default value if not found.
     * @return mixed The attribute value.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    /**
     * Determine if the user has a specific role.
     *
     * @param  string  $role
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        $roles = $this->attributes['roles'] ?? [];

        return in_array($role, array_column($roles, 'name'), true);
    }

    /**
     * Determine if the user has a specific permission.
     *
     * @param  string  $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->attributes['permissions'] ?? [];

        return in_array($permission, array_column($permissions, 'name'), true);
    }

    /**
     * Get the user's organisation.
     *
     * @return array|null
     */
    public function organisation(): ?array
    {
        return $this->attributes['organisation'] ?? null;
    }
}
