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

    /** API base URI */
    public const API_URI = 'https://dev-api.cerberus-iam.com';

    /** API version */
    public const API_VERSION = 'v1';

    /** Header: client ID */
    public const API_KEY_NAME = 'X-Cerberus-Client-Id';

    /** Header: client secret */
    public const API_SECRET_NAME = 'X-Cerberus-Client-Secret';

    /** Header: testing mode */
    public const API_TESTING_MODE = 'X-Cerberus-Testing';

    /**
     * Cerberus constructor.
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
     * Enable testing mode via header.
     */
    public function testing(): static
    {
        $this->http->withHeader(self::API_TESTING_MODE, true);

        return $this;
    }
}
