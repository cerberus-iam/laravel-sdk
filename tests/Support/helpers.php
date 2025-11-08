<?php

use CerberusIAM\Http\Clients\CerberusClient;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Support\Arr;

if (! function_exists('cerberusHelperHttpFactory')) {
    function cerberusHelperHttpFactory(): HttpFactory
    {
        return app(HttpFactory::class);
    }
}

if (! function_exists('cerberusLiveConfig')) {
    /**
     * Resolve and cache live test configuration.
     *
     * @return array<string, mixed>
     */
    function cerberusLiveConfig(): array
    {
        static $config;

        if ($config !== null) {
            return $config;
        }

        $interactiveScopes = cerberusNormalizeScopes(env('CERBERUS_IAM_SCOPES', env('CERBERUS_IAM_SCOPES', 'openid profile email')));
        $adminScopes = cerberusNormalizeScopes(env('CERBERUS_IAM_ADMIN_SCOPES', 'users:read'));
        $scopes = array_values(array_unique([...$interactiveScopes, ...$adminScopes]));

        return $config = [
            'base_url' => rtrim((string) env('CERBERUS_IAM_BASE_URL', env('CERBERUS_IAM_URL', 'https://api.cerberus-iam.com')), '/'),
            'client_id' => env('CERBERUS_IAM_CLIENT_ID', env('CERBERUS_IAM_CLIENT_ID')),
            'client_secret' => env('CERBERUS_IAM_CLIENT_SECRET', env('CERBERUS_IAM_CLIENT_SECRET')),
            'redirect_uri' => env('CERBERUS_IAM_REDIRECT_URI', env('CERBERUS_IAM_REDIRECT_URI', 'https://example.test/cerberus/callback')),
            'username' => env('CERBERUS_IAM_USERNAME', env('CERBERUS_IAM_USERNAME')),
            'password' => env('CERBERUS_IAM_PASSWORD', env('CERBERUS_IAM_PASSWORD')),
            'organisation_slug' => env('CERBERUS_IAM_ORG_SLUG', env('CERBERUS_IAM_ORG_SLUG')),
            'session_cookie' => env('CERBERUS_IAM_SESSION_COOKIE', env('CERBERUS_IAM_SESSION_COOKIE', 'cerb_sid')),
            'scopes' => $scopes,
        ];
    }
}

if (! function_exists('cerberusLiveConfigMissing')) {
    /**
     * Determine if a required configuration key is missing.
     *
     * @param  array<int, string>  $keys
     */
    function cerberusLiveConfigMissing(array $keys): bool
    {
        $config = cerberusLiveConfig();

        foreach ($keys as $key) {
            if (blank(Arr::get($config, $key))) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('cerberusLiveClient')) {
    /**
     * Instantiate a CerberusClient configured for the live API.
     */
    function cerberusLiveClient(?string $organisationSlug = null): CerberusClient
    {
        $config = cerberusLiveConfig();

        return new CerberusClient(
            $config['base_url'],
            $config['session_cookie'],
            $organisationSlug ?? $config['organisation_slug'],
            [
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $config['redirect_uri'],
                'scopes' => ['users:read', 'roles:read'],
            ],
            [
                'timeout' => (int) env('CERBERUS_IAM_TIMEOUT', 15),
            ],
            cerberusHelperHttpFactory()
        );
    }
}

if (! function_exists('cerberusLiveLogin')) {
    /**
     * Perform a live login request and return session context.
     *
     * @return array<string, mixed>
     */
    function cerberusLiveLogin(): array
    {
        $config = cerberusLiveConfig();

        $http = cerberusHelperHttpFactory();

        $response = $http->baseUrl($config['base_url'])
            ->acceptJson()
            ->timeout((int) env('CERBERUS_IAM_TIMEOUT', 15))
            ->post('/v1/auth/login', [
                'email' => $config['username'],
                'password' => $config['password'],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf('Cerberus login failed with status %s: %s', $response->status(), $response->body()));
        }

        $payload = $response->json();

        $sessionToken = cerberusExtractCookie($response, (string) $config['session_cookie']);

        if (! $sessionToken) {
            throw new RuntimeException('Cerberus login response did not return the expected session cookie.');
        }

        $tokens = $payload['tokens'] ?? ($payload['token'] ?? []);
        if (! is_array($tokens)) {
            $tokens = [];
        }

        return [
            'session_token' => $sessionToken,
            'user' => Arr::get($payload, 'user', []),
            'organisation' => Arr::get($payload, 'organisation', []),
            'tokens' => $tokens,
        ];
    }
}

if (! function_exists('cerberusExtractCookie')) {
    /**
     * Extract a cookie value from an HTTP response.
     */
    function cerberusExtractCookie(HttpResponse $response, string $cookieName): ?string
    {
        $cookie = $response->cookies()->getCookieByName($cookieName);

        if ($cookie && method_exists($cookie, 'getValue')) {
            return $cookie->getValue();
        }

        foreach ((array) $response->header('Set-Cookie') as $header) {
            if (preg_match('/'.preg_quote($cookieName, '/').'=([^;]+)/', $header, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}

if (! function_exists('cerberusLiveOrganisationSlug')) {
    /**
     * Resolve the organisation slug to use for admin endpoints.
     *
     * @param  array<string, mixed>  $login
     */
    function cerberusLiveOrganisationSlug(array $login): ?string
    {
        return Arr::get($login, 'organisation.slug') ?: Arr::get(cerberusLiveConfig(), 'organisation_slug');
    }
}

if (! function_exists('cerberusNormalizeScopes')) {
    /**
     * Convert a string of scopes to an array.
     *
     * @return array<int, string>
     */
    function cerberusNormalizeScopes(?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        return array_values(array_filter(preg_split('/\s+/', $value) ?: []));
    }
}

if (! function_exists('cerberusLiveTestsEnabled')) {
    /**
     * Determine if live integration tests are enabled.
     */
    function cerberusLiveTestsEnabled(): bool
    {
        $flag = env('CERBERUS_IAM_LIVE_TESTS', false);

        if (is_bool($flag)) {
            return $flag;
        }

        return filter_var((string) $flag, FILTER_VALIDATE_BOOL);
    }
}
