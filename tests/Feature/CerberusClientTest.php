<?php

namespace CerberusIAM\Tests\Feature;

use CerberusIAM\Http\Clients\CerberusClient;
use CerberusIAM\Tests\Support\FetchStub;

class StubResponse
{
    public function __construct(
        protected bool $ok,
        protected array $payload,
        protected string $text = ''
    ) {}

    public function successful(): bool
    {
        return $this->ok;
    }

    public function json(): array
    {
        return $this->payload;
    }

    public function text(): string
    {
        return $this->text;
    }
}

it('fetches a user by id using client credentials', function () {
    FetchStub::$requests = [];
    FetchStub::$queue = [
        new StubResponse(true, [
            'access_token' => 'app-token',
            'expires_in' => 3600,
        ]),
        new StubResponse(true, [
            'id' => 'user-123',
            'email' => 'admin@example.com',
        ]),
    ];

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
        []
    );

    $user = $client->getUserById('user-123');

    expect($user)->toMatchArray([
        'id' => 'user-123',
        'email' => 'admin@example.com',
    ]);

    expect(FetchStub::$requests)->toHaveCount(2);

    $tokenRequest = FetchStub::$requests[0];
    expect($tokenRequest['url'])->toBe('https://cerb.test/oauth2/token');
    expect($tokenRequest['options']['method'])->toBe('POST');
    expect($tokenRequest['options']['headers']['Authorization'])->toStartWith('Basic ');

    $userRequest = FetchStub::$requests[1];
    expect($userRequest['url'])->toBe('https://cerb.test/v1/admin/users/user-123');
    expect($userRequest['options']['headers']['Authorization'])->toBe('Bearer app-token');
    expect($userRequest['options']['headers']['X-Org-Domain'])->toBe('cerberus-iam');
});
