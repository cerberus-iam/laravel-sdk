<?php

namespace Cerberus;

use Cerberus\Concerns\HandlesAccessToken;
use Cerberus\Concerns\HandlesApiConfigurations;
use Cerberus\Concerns\HandlesImpersonation;
use Cerberus\Concerns\ResolvesResources;
use Fetch\Interfaces\ClientHandler;

class Cerberus
{
    use HandlesAccessToken,
        HandlesApiConfigurations,
        HandlesImpersonation,
        ResolvesResources;

    /**
     * The base URI for the Cerberus API.
     *
     * @var string
     */
    public const API_URI = 'https://dev-api.cerberus-iam.com';

    /**
     * The version of the Cerberus API.
     *
     * @var string
     */
    public const API_VERSION = 'v1';

    /**
     * The header name for the Cerberus client ID.
     *
     * @var string
     */
    public const API_KEY_NAME = 'X-Cerberus-Client-Id';

    /**
     * The header name for the Cerberus client secret.
     *
     * @var string
     */
    public const API_SECRET_NAME = 'X-Cerberus-Client-Secret';

    /**
     * The header name for enabling testing mode.
     *
     * @var string
     */
    public const API_TESTING_MODE = 'X-Cerberus-Testing';

    /**
     * The grant type for authentication.
     *
     * @var string
     */
    public const GRANT_TYPE = 'client_credentials';

    /**
     * The cache key for storing the client access token.
     *
     * @var string
     */
    public const CACHE_KEY_TOKEN = 'cerberus.client_access_token';

    /**
     * Create a new Cerberus instance.
     *
     * @return void
     */
    public function __construct(protected ClientHandler $http)
    {
        $this->configureAccessToken();
    }

    /**
     * Get the HTTP client instance.
     */
    public function getHttpClient(): ClientHandler
    {
        return $this->http;
    }

    /**
     * Enable testing mode for the Cerberus client.
     */
    public function testing(): static
    {
        $this->http->withHeader(self::API_TESTING_MODE, true);

        return $this;
    }
}
