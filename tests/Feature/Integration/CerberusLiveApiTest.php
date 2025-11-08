<?php

declare(strict_types=1);

use CerberusIAM\Repositories\UserDirectoryRepository;
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
    ->skip(
        fn () => ! cerberusLiveTestsEnabled()
            || cerberusLiveConfigMissing(['base_url', 'username', 'password']),
        'Set CERBERUS_IAM_* environment variables and enable CERBERUS_IAM_LIVE_TESTS=true to run live integration tests.'
    );

test('retrieves a user by id from the live admin API', function () {
    $login = cerberusLiveLogin();
    $organisationSlug = cerberusLiveOrganisationSlug($login);

    if (! $organisationSlug) {
        $this->markTestSkipped('Provide CERBERUS_IAM_ORG_SLUG or ensure the login response contains organisation.slug.');
    }

    $client = cerberusLiveClient($organisationSlug);

    $user = $client->getUserById(Arr::get($login, 'user.id'));

    if (! $user) {
        $this->markTestSkipped('Client credentials must be allowed to call /v1/admin/users for this organisation (set CERBERUS_IAM_ADMIN_SCOPES and grant API access).');
    }

    expect($user)->toBeArray()
        ->and(Arr::get($user, 'id'))->toBe(Arr::get($login, 'user.id'));
})
    ->group('integration')
    ->skip(
        fn () => ! cerberusLiveTestsEnabled()
            || cerberusLiveConfigMissing(['base_url', 'client_id', 'client_secret', 'username', 'password']),
        'Set CERBERUS_IAM_* environment variables and enable CERBERUS_IAM_LIVE_TESTS=true to run live integration tests.'
    );

test('lists organisation users via the live directory endpoint', function () {
    $login = cerberusLiveLogin();
    $organisationSlug = cerberusLiveOrganisationSlug($login);

    if (! $organisationSlug) {
        $this->markTestSkipped('Provide CERBERUS_IAM_ORG_SLUG or ensure the login response contains organisation.slug.');
    }

    $client = cerberusLiveClient($organisationSlug);
    $repository = new UserDirectoryRepository($client);

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
    ->skip(
        fn () => ! cerberusLiveTestsEnabled()
            || cerberusLiveConfigMissing(['base_url', 'username', 'password']),
        'Set CERBERUS_IAM_* environment variables and enable CERBERUS_IAM_LIVE_TESTS=true to run live integration tests.'
    );
