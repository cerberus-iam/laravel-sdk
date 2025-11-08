<?php

declare(strict_types=1);

use CerberusIAM\Http\Clients\CerberusClient;
use CerberusIAM\Repositories\UserDirectoryRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

test('fetches the current user profile via a live Cerberus session', function () {
    $login = cerberusLiveLogin();

    $client = cerberusLiveClient();

    $profile = $client->getCurrentUserFromSession($login['session_token']);

    expect($profile)->toBeArray()
        ->and(Arr::get($profile, 'id'))->toBe(Arr::get($login, 'user.id'));

    $client->logoutSession($login['session_token']);
})
    ->group('integration')
    ->skip(fn () => cerberusLiveConfigMissing(['base_url', 'username', 'password']), 'Set CERBERUS_IAM_* environment variables to run live integration tests.');

test('retrieves a user by id from the live admin API', function () {
    $login = cerberusLiveLogin();
    $organisationSlug = cerberusLiveOrganisationSlug($login);

    if (! $organisationSlug) {
        $this->markTestSkipped('Provide CERBERUS_IAM_ORG_SLUG or ensure the login response contains organisation.slug.');
    }

    $client = cerberusLiveClient($organisationSlug);

    $user = $client->getUserById(Arr::get($login, 'user.id'));

    expect($user)->toBeArray()
        ->and(Arr::get($user, 'id'))->toBe(Arr::get($login, 'user.id'));
})
    ->group('integration')
    ->skip(fn () => cerberusLiveConfigMissing(['base_url', 'client_id', 'client_secret', 'username', 'password']), 'Set CERBERUS_IAM_* environment variables to run live integration tests.');

test('lists organisation users via the live directory endpoint', function () {
    $login = cerberusLiveLogin();
    $organisationSlug = cerberusLiveOrganisationSlug($login);

    if (! $organisationSlug) {
        $this->markTestSkipped('Provide CERBERUS_IAM_ORG_SLUG or ensure the login response contains organisation.slug.');
    }

    $client = cerberusLiveClient($organisationSlug);
    $repository = new UserDirectoryRepository($client, app(HttpFactory::class));

    $request = Request::create('/users', 'GET', ['per_page' => 5]);

    $payload = $repository->list(
        $organisationSlug,
        $request,
        ['per_page' => 5],
        null,
        $login['session_token']
    );

    expect($payload['data'] ?? [])->not->toBeEmpty();

    $client->logoutSession($login['session_token']);
})
    ->group('integration')
    ->skip(fn () => cerberusLiveConfigMissing(['base_url', 'username', 'password']), 'Set CERBERUS_IAM_* environment variables to run live integration tests.');

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
            'scopes' => $config['scopes'],
        ],
        [
            'timeout' => (int) env('CERBERUS_IAM_TIMEOUT', 15),
        ],
        app(HttpFactory::class)
    );
}

/**
 * Perform a live login request and return session context.
 *
 * @return array<string, mixed>
 */
function cerberusLiveLogin(): array
{
    $config = cerberusLiveConfig();

    /** @var HttpFactory $http */
    $http = app(HttpFactory::class);

    $response = $http->baseUrl($config['base_url'])
        ->acceptJson()
        ->timeout((int) env('CERBERUS_IAM_TIMEOUT', 15))
        ->post('/v1/auth/login', [
            'email' => $config['username'],
            'password' => $config['password'],
        ]);

    if ($response->failed()) {
        throw new RuntimeException(sprintf(
            'Cerberus login failed with status %s: %s',
            $response->status(),
            $response->body()
        ));
    }

    $sessionToken = cerberusExtractCookie($response, (string) $config['session_cookie']);

    if (! $sessionToken) {
        throw new RuntimeException('Cerberus login response did not return the expected session cookie.');
    }

    return [
        'session_token' => $sessionToken,
        'user' => Arr::get($response->json(), 'user', []),
        'organisation' => Arr::get($response->json(), 'organisation', []),
    ];
}

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
            return $matches[1] ?? null;
        }
    }

    return null;
}

/**
 * Resolve the organisation slug to use for admin endpoints.
 *
 * @param  array<string, mixed>  $login
 */
function cerberusLiveOrganisationSlug(array $login): ?string
{
    return Arr::get($login, 'organisation.slug') ?: Arr::get(cerberusLiveConfig(), 'organisation_slug');
}

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
