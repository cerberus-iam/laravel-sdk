<?php

namespace Cerberus\Resources;

use BackedEnum;
use Exception;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class User extends Resource implements AuthenticatableContract, AuthorizableContract, CanResetPasswordContract
{
    use Authenticatable, CanResetPassword, MustVerifyEmail;

    /**
     * Name of the resource.
     */
    public string $resource = 'users';

    /**
     * The access token for the user.
     */
    protected string $accessToken;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected array $fillable = [
        'uid',
        'email',
        'first_name',
        'last_name',
        'username',
        'phone',
        'password',
        'remember_token',
        'email_verified_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Get the password hash for the user.
     */
    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    /**
     * Get the remember token name for the user.
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(Role $role): self
    {
        $this->getConnection()
            ->post('/users/'.$this->uid.'/roles', [
                'roles' => [$role->name],
            ]);

        return $this;
    }

    /**
     * Determine if the user has the given role[s].
     */
    public function hasRoles(array $roles): bool
    {
        $userRoles = $this->getConnection()
            ->get('/users/'.$this->uid.'/roles')
            ->json('data');

        foreach ($userRoles as $userRole) {
            if (in_array($userRole['name'], $roles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the entity has the given abilities.
     *
     * @param  iterable|BackedEnum|string  $abilities
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function can($abilities, $arguments = [])
    {
        return Container::getInstance()
            ->make(Gate::class)
            ->forUser($this)
            ->check($abilities, $arguments);
    }

    /**
     * Determine if the entity has any of the given abilities.
     *
     * @param  iterable|BackedEnum|string  $abilities
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function canAny($abilities, $arguments = [])
    {
        return Container::getInstance()
            ->make(Gate::class)
            ->forUser($this)
            ->any($abilities, $arguments);
    }

    /**
     * Determine if the entity does not have the given abilities.
     *
     * @param  iterable|BackedEnum|string  $abilities
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function cant($abilities, $arguments = [])
    {
        return ! $this->can($abilities, $arguments);
    }

    /**
     * Determine if the entity does not have the given abilities.
     *
     * @param  iterable|BackedEnum|string  $abilities
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function cannot($abilities, $arguments = [])
    {
        return $this->cant($abilities, $arguments);
    }

    /**
     * Get the remember token for the user.
     */
    public function notify($notification): void
    {
        // Optional: send via Cerberus or throw an exception if not supported
        throw new Exception('Notifications are not supported in Cerberus SDK yet.');
    }

    /**
     * Get the access token for the user.
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * Set the access token for the user.
     */
    public function withToken(string $token): self
    {
        $this->accessToken = $token;

        return $this;
    }
}
