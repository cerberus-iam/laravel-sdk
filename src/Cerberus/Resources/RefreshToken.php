<?php

namespace Cerberus\Resources;

class RefreshToken extends Token
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
}
