<?php

namespace CerberusIAM\Tests\Feature;

use CerberusIAM\Http\Clients\CerberusClient;
use Illuminate\Support\Facades\Http;

it('fetches a user by id using client credentials', function () {
    Http::fakeSequence()
        ->push([
            'access_token' => 'app-token',
            'expires_in' => 3600,
        ], 200)
        ->push([
            'id' => 'user-123',
            'email' => 'admin@example.com',
        ], 200);

    $client = new CerberusClient(
        'https://cerb.test',
        'cerb_sid',
        'cerberus-iam',
        [
            'client_id' => 'client-id',
            'client_secret' => 'top-secret',
            'redirect_uri' => 'https://app.test/callback',
            'scopes' => ['users:read'],
        ],
        [],
        app(\Illuminate\Http\Client\Factory::class)
    );

    $user = $client->getUserById('user-123');

    expect($user)->toMatchArray([
        'id' => 'user-123',
        'email' => 'admin@example.com',
    ]);

    Http::assertSentCount(2);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://cerb.test/oauth2/token'
            && $request->method() === 'POST'
            && str_starts_with($request->header('Authorization')[0] ?? '', 'Basic ');
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'https://cerb.test/v1/admin/users/user-123'
            && $request->header('Authorization')[0] === 'Bearer app-token'
            && $request->header('X-Org-Domain')[0] === 'cerberus-iam';
    });
});
