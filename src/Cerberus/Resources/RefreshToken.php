<?php

namespace Cerberus\Resources;

use Illuminate\Support\Carbon;

class RefreshToken extends Resource
{
    /**
     * The resource name.
     */
    public string $resource = 'refresh_tokens';

    /**
     * The primary key for this resource.
     */
    protected string $primaryKey = 'refresh_token';

    /**
     * Create a new RefreshToken resource instance.
     */
    public function __construct(array $attributes = [])
    {
        if (isset($attributes['expires_in']) && ! isset($attributes['expires_at'])) {
            $attributes['expires_at'] = now()->addSeconds($attributes['expires_in']);
        }

        parent::__construct($attributes);
    }

    /**
     * Get the raw refresh token string.
     */
    public function getRefreshToken(): string
    {
        return $this->getAttribute('refresh_token');
    }

    /**
     * Get the token ID if available.
     */
    public function getTokenId(): ?string
    {
        return $this->getAttribute('token_id');
    }

    /**
     * Get the access token ID this refresh token is associated with.
     */
    public function getAccessTokenId(): ?string
    {
        return $this->getAttribute('access_token_id');
    }

    /**
     * Get the expiration timestamp.
     */
    public function expiresAt(): ?Carbon
    {
        return $this->getAttribute('expires_at');
    }

    /**
     * Check if the refresh token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt()?->isPast() ?? true;
    }
}
