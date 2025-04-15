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
     * Set the client ID and secret for authentication.
     */
    public function useClientCredentials(string $id, string $secret): self
    {
        $this->clientIdOverride = $id;
        $this->clientSecretOverride = $secret;

        return $this;
    }
}
