<?php

namespace Cerberus\Concerns;

use Cerberus\Cerberus;
use Cerberus\Resources\Token;
use Cerberus\TokenParser;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

trait HandlesAccessToken
{
    /**
     * Configure the access token on the HTTP client.
     */
    public function configureAccessToken(): self
    {
        if (! $this->http->hasHeader('Authorization')) {
            $this->http->withToken($this->getAccessToken()['access_token']);
        }

        return $this;
    }

    /**
     * Get the access token from cache or request a new one.
     *
     * @return array{access_token: string, expires_in: int}
     */
    public function getAccessToken(): array
    {
        $cached = Cache::get(Cerberus::CACHE_KEY_TOKEN);

        if (is_array($cached) && isset($cached['access_token'], $cached['expires_in'])) {
            return $cached;
        }

        $response = $this->http->post('/oauth/token', [
            'grant_type' => Cerberus::GRANT_TYPE,
            'client_id' => config('services.cerberus.key'),
            'client_secret' => config('services.cerberus.secret'),
            'scope' => '*',
        ]);

        if (! $response->ok()) {
            throw new RuntimeException('Failed to fetch Cerberus client access token.');
        }

        $data = $response->json();

        if (! isset($data['access_token'], $data['expires_in'])) {
            throw new RuntimeException('Invalid access token response from Cerberus.');
        }

        Cache::put(Cerberus::CACHE_KEY_TOKEN, $data, now()->addSeconds($data['expires_in']));

        return $data;
    }

    /**
     * Return a parsed Token resource from the current access token.
     */
    public function parsedToken(): Token
    {
        return TokenParser::parse($this->getAccessToken()['access_token']);
    }
}
