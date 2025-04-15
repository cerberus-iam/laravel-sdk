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
        return rtrim(sprintf(
            '%s/%s',
            config('services.cerberus.url', self::API_URI),
            self::API_VERSION
        ), '/');
    }

    /**
     * Get default HTTP headers for Cerberus client.
     */
    public static function getHttpHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            self::API_KEY_NAME => config()->get('services.cerberus.key'),
            self::API_SECRET_NAME => config()->get('services.cerberus.secret'),
        ];
    }

    /**
     * Set the client ID and secret for authentication using a client model.
     */
    public function useClient(Model $client): self
    {
        return $this->useClientCredentials(
            $client->getKey(),
            $client->plainSecret ?? $client->secret
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
