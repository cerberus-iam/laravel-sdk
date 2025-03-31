<?php

namespace Cerberus\Resources;

use Fetch\Interfaces\ClientHandler;
use Illuminate\Support\Carbon;

class Token extends Resource
{
    /**
     * The resource name.
     */
    protected string $resource = 'tokens';

    /**
     * The primary key for this resource.
     */
    protected string $primaryKey = 'access_token';

    /**
     * Create a new Token resource instance.
     */
    public function __construct(ClientHandler $connection, array $attributes = [])
    {
        if (isset($attributes['expires_in']) && ! isset($attributes['expires_at'])) {
            $attributes['expires_at'] = now()->addSeconds($attributes['expires_in']);
        }

        parent::__construct($connection, $attributes);
    }

    /**
     * Get the raw access token string.
     */
    public function getAccessToken(): string
    {
        return (string) $this->getAttribute('access_token');
    }

    /**
     * Get the associated client ID.
     */
    public function getClientId(): ?string
    {
        return $this->getAttribute('client_id');
    }

    /**
     * Get the associated user ID (if any).
     */
    public function getUserId(): ?int
    {
        $id = $this->getAttribute('user_id');

        return is_null($id) ? null : (int) $id;
    }

    /**
     * Get the token ID (e.g. JWT jti claim).
     */
    public function getTokenId(): ?string
    {
        return $this->getAttribute('token_id');
    }

    /**
     * Get the token expiration timestamp.
     */
    public function expiresAt(): ?Carbon
    {
        $timestamp = $this->getAttribute('expires_at');

        return $timestamp instanceof Carbon
            ? $timestamp
            : Carbon::parse($timestamp);
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt()?->isPast() ?? true;
    }

    /**
     * Get the scopes granted to this token.
     *
     * @return array<string>
     */
    public function scopes(): array
    {
        return (array) $this->getAttribute('scopes');
    }

    /**
     * Check if the token has the given scope.
     */
    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes(), true);
    }

    /**
     * Check if the token has any of the given scopes.
     */
    public function hasAnyScope(array $scopes): bool
    {
        return ! empty(array_intersect($this->scopes(), $scopes));
    }

    /**
     * Determine if the token belongs to a user (Password Grant).
     */
    public function isUserToken(): bool
    {
        return ! is_null($this->getUserId());
    }

    /**
     * Determine if the token is a Client Credentials token.
     */
    public function isClientToken(): bool
    {
        return ! $this->isUserToken();
    }
}
