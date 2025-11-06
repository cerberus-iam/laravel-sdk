<?php

declare(strict_types=1);

namespace CerberusIAM\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;

/**
 * Cerberus User
 *
 * This class represents a user authenticated via Cerberus IAM,
 * implementing Laravel's Authenticatable interface.
 */
class CerberusUser implements Authenticatable
{
    protected array $attributes;

    /**
     * Create a new Cerberus user instance.
     *
     * @param  array<string, mixed>  $attributes  The user attributes.
     */
    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

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
        return new self([
            'id' => $payload['id'] ?? $payload['sub'] ?? null,
            'email' => $payload['email'] ?? null,
            'name' => $payload['name'] ?? trim(($payload['firstName'] ?? '').' '.($payload['lastName'] ?? '')),
            'first_name' => $payload['firstName'] ?? null,
            'last_name' => $payload['lastName'] ?? null,
            'organisation' => Arr::only($payload['organisation'] ?? [], ['id', 'slug', 'name']),
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
        return Arr::get($this->attributes, $key, $default);
    }

    /**
     * Convert the user to an array.
     *
     * @return array<string, mixed> The user attributes.
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
