<?php

namespace Cerberus\Concerns;

use Fetch\Interfaces\ClientHandler;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

trait HandlesApiConfigurations
{
    /**
     * Override for client ID.
     */
    protected ?string $clientIdOverride = null;

    /**
     * Override for client secret.
     */
    protected ?string $clientSecretOverride = null;

    /**
     * Get base URI for Cerberus API.
     */
    public static function getBaseUri(): string
    {
        $base = config('services.cerberus.url') ?: self::API_URI;

        return rtrim($base, '/').'/'.self::API_VERSION;
    }

    /**
     * Get default HTTP headers.
     *
     * @return array<string, string>
     */
    public static function getHttpHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            self::API_KEY_NAME => config('services.cerberus.key'),
            self::API_SECRET_NAME => config('services.cerberus.secret'),
        ];
    }

    /**
     * Use a Laravel Model for client credentials.
     *
     * @throws \RuntimeException
     */
    public function useClient(Model $client): self
    {
        $secret = $client->plainSecret ?? $client->secret;

        if (! $secret) {
            throw new RuntimeException('Client secret is missing.');
        }

        return $this->useClientCredentials((string) $client->getKey(), $secret);
    }

    /**
     * Override client credentials and purge existing token.
     */
    public function useClientCredentials(string $id, string $secret): self
    {
        $this->clientIdOverride = $id;
        $this->clientSecretOverride = $secret;

        $this->getTokenStorage()->forget();

        return $this;
    }

    /**
     * Add client overrides headers to the HTTP handler.
     */
    protected function applyClientOverrides(ClientHandler $http): void
    {
        if (! is_null($this->clientIdOverride) && ! is_null($this->clientSecretOverride)) {
            $http->withHeader(static::API_KEY_NAME, $this->clientIdOverride)
                ->withHeader(static::API_SECRET_NAME, $this->clientSecretOverride);
        }
    }
}
