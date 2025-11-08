<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

test('refreshes live oauth tokens via refresh token grant', function () {
    $login = cerberusLiveLogin();
    $refreshToken = Arr::get($login, 'tokens.refresh_token');

    if (! $refreshToken) {
        $this->markTestSkipped('The live login response did not include a refresh token.');
    }

    $client = cerberusLiveClient();

    $tokens = $client->refreshAccessToken($refreshToken);

    expect($tokens)->toBeArray()
        ->and($tokens)->toHaveKeys(['access_token', 'refresh_token', 'token_type', 'expires_in']);
})
    ->group('integration')
    ->skip(
        fn () => ! cerberusLiveTestsEnabled()
            || cerberusLiveConfigMissing(['base_url', 'client_id', 'client_secret', 'username', 'password']),
        'Set CERBERUS_IAM_* environment variables and enable CERBERUS_IAM_LIVE_TESTS=true to run live integration tests.'
    );

test('throws when refresh token is invalid via live oauth endpoint', function () {
    $client = cerberusLiveClient();

    expect(fn () => $client->refreshAccessToken(Str::random(40)))
        ->toThrow(\RuntimeException::class, 'Cerberus token request failed:');
})
    ->group('integration')
    ->skip(
        fn () => ! cerberusLiveTestsEnabled()
            || cerberusLiveConfigMissing(['base_url', 'client_id']),
        'Set CERBERUS_IAM_* environment variables and enable CERBERUS_IAM_LIVE_TESTS=true to run live integration tests.'
    );
