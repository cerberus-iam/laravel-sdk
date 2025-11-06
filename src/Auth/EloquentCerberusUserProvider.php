<?php

declare(strict_types=1);

namespace CerberusIAM\Auth;

use CerberusIAM\Contracts\IamClient;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent Cerberus User Provider
 *
 * This provider syncs users from Cerberus IAM to a local Eloquent model,
 * allowing for better performance and integration with Laravel's ecosystem.
 */
class EloquentCerberusUserProvider implements UserProvider
{
    /**
     * Create a new Eloquent Cerberus user provider instance.
     *
     * @param  IamClient  $client  The IAM client for API communication.
     * @param  string  $model  The Eloquent model class name.
     */
    public function __construct(
        protected IamClient $client,
        protected string $model
    ) {}

    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier  The user identifier.
     * @return Authenticatable|null The user instance, or null if not found.
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        $model = $this->createModel();

        return $model->newQuery()
            ->where($model->getAuthIdentifierName(), $identifier)
            ->first();
    }

    /**
     * Retrieve a user by their remember token.
     *
     * @param  mixed  $identifier  The user identifier.
     * @param  string  $token  The remember token.
     * @return Authenticatable|null The user instance, or null if not found.
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $model = $this->createModel();

        $retrievedModel = $model->newQuery()
            ->where($model->getAuthIdentifierName(), $identifier)
            ->first();

        if (! $retrievedModel) {
            return null;
        }

        $rememberToken = $retrievedModel->getRememberToken();

        return $rememberToken && hash_equals($rememberToken, $token)
            ? $retrievedModel
            : null;
    }

    /**
     * Update the remember token for a user.
     *
     * @param  Authenticatable  $user  The user instance.
     * @param  string  $token  The remember token.
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $user->setRememberToken($token);

        $timestamps = $user->timestamps;

        $user->timestamps = false;

        $user->save();

        $user->timestamps = $timestamps;
    }

    /**
     * Retrieve a user by credentials (not supported).
     *
     * @param  array<string, mixed>  $credentials  The credentials.
     * @return Authenticatable|null Always returns null.
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        // Password-based authentication is not supported
        return null;
    }

    /**
     * Validate user credentials (not supported).
     *
     * @param  Authenticatable  $user  The user instance.
     * @param  array<string, mixed>  $credentials  The credentials.
     * @return bool Always returns false.
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        // Password validation is not supported for OAuth
        return false;
    }

    /**
     * Rehash the user's password if required (not applicable).
     *
     * @param  Authenticatable  $user  The user instance.
     * @param  array<string, mixed>  $credentials  The credentials.
     * @param  bool  $force  Whether to force rehashing.
     * @return bool Always returns false.
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): bool
    {
        // Password management is delegated to Cerberus IAM
        return false;
    }

    /**
     * Sync a user from Cerberus IAM to the local database.
     *
     * This method creates or updates a local user record based on data from Cerberus.
     *
     * @param  array<string, mixed>  $profile  The user profile from Cerberus.
     * @return Authenticatable The synced user instance.
     */
    public function syncUser(array $profile): Authenticatable
    {
        $model = $this->createModel();

        // Extract the user ID
        $userId = $profile['id'] ?? $profile['sub'] ?? null;

        if (! $userId) {
            throw new \RuntimeException('User profile must contain an id or sub field');
        }

        // Prepare user data for local storage
        $userData = [
            'email' => $profile['email'] ?? null,
            'name' => $profile['name'] ?? trim(($profile['firstName'] ?? '').' '.($profile['lastName'] ?? '')),
            'cerberus_id' => $userId,
        ];

        // Add optional fields if they exist in the model's fillable attributes
        $fillable = $model->getFillable();

        if (in_array('first_name', $fillable)) {
            $userData['first_name'] = $profile['firstName'] ?? null;
        }

        if (in_array('last_name', $fillable)) {
            $userData['last_name'] = $profile['lastName'] ?? null;
        }

        if (in_array('organisation_id', $fillable)) {
            $userData['organisation_id'] = $profile['organisation']['id'] ?? null;
        }

        if (in_array('organisation_slug', $fillable)) {
            $userData['organisation_slug'] = $profile['organisation']['slug'] ?? null;
        }

        // Use updateOrCreate to sync the user
        $user = $model->newQuery()->updateOrCreate(
            ['cerberus_id' => $userId],
            $userData
        );

        return $user;
    }

    /**
     * Create a new instance of the model.
     *
     * @return Model The model instance.
     */
    protected function createModel(): Model
    {
        $class = '\\'.ltrim($this->model, '\\');

        return new $class;
    }
}
