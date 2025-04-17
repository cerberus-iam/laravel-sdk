<?php

namespace Cerberus\Concerns;

use Illuminate\Database\Eloquent\Model;

trait HandlesApiConfigurations
{
    /**
     * The override for the client ID.
     */
    protected ?string $clientIdOverride = null;

    /**
     * The override for the client secret.
     */
    protected ?string $clientSecretOverride = null;

    /**
     * Get the base URI for Cerberus API.
     */
    public static function getBaseUri(): string
    {
        $baseUrl = config('services.cerberus.url');

        // Ensure we have a valid base URL
        if (empty($baseUrl) || $baseUrl === '/') {
            $baseUrl = self::API_URI;
        }

        // Ensure baseUrl doesn't end with a slash
        $baseUrl = rtrim($baseUrl, '/');

        return sprintf('%s/%s', $baseUrl, self::API_VERSION);
    }

    /**
     * Get default HTTP headers for Cerberus client.
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
     * Set the client ID and secret for authentication using a client model.
     */
    public function useClient(Model $client): self
    {
        $secret = $client->plainSecret ?? ($client->secret ?? null);

        if (! $secret) {
            throw new \RuntimeException('Client secret is missing.');
        }

        return $this->useClientCredentials(
            (string) $client->getKey(),
            $secret
        );
    }

    /**
     * Override the client credentials used for token requests.
     *
     * @param  string  $clientId  The client ID to use
     * @param  string  $clientSecret  The client secret to use
     */
    public function useClientCredentials(string $clientId, string $clientSecret): self
    {
        $this->clientIdOverride = $clientId;
        $this->clientSecretOverride = $clientSecret;

        // Important: When client credentials change, we need to forget existing tokens
        // as they were issued for different client credentials
        $this->getTokenStorage()->forget();

        return $this;
    }
}
