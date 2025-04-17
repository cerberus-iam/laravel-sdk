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
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected array $fillable = [
        'refresh_token',
        'token_id',
        'expires_in',
    ];

    /**
     * Create a new RefreshToken resource instance.
     */
    public function __construct(array $attributes = [])
    {
        if (isset($attributes['expires_in'])) {
            $attributes['expires_in'] = now()->addSeconds($attributes['expires_in']);
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
     * Set the proper expires in time.
     */
    public function setExpiresIn(int $expiresIn): self
    {
        $this->attributes['expires_in'] = now()->addSeconds($expiresIn);

        return $this;
    }

    /**
     * Get the expiration timestamp.
     */
    public function expiresAt(): ?Carbon
    {
        return $this->getAttribute('expires_in');
    }

    /**
     * Check if the refresh token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt()?->isPast() ?? true;
    }
}
