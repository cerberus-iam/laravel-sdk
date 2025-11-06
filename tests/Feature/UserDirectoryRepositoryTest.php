<?php

namespace CerberusIAM\Tests\Feature;

use CerberusIAM\Contracts\IamClient;
use CerberusIAM\Repositories\UserDirectoryRepository;
use CerberusIAM\Tests\Fixtures\FakeIamClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

it('builds requests with filter parameters', function () {
    $fake = new FakeIamClient;
    app()->instance(IamClient::class, $fake);

    Http::fake(fn () => Http::response(['data' => []], 200));

    $repository = new UserDirectoryRepository(
        $fake,
        app(\Illuminate\Http\Client\Factory::class)
    );

    $request = Request::create('/users', 'GET', [
        'email' => 'jane@example.com',
        'team' => 'ops',
    ]);

    $repository->list('acme', $request, ['page' => 2], 'access-token', 'session-token');

    Http::assertSentCount(1);

    Http::assertSent(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

        return $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://cerberus.test/v1/admin/users')
            && $request->header('X-Org-Domain')[0] === 'acme'
            && $request->header('Authorization')[0] === 'Bearer access-token'
            && $request->header('Cookie')[0] === 'cerb_sid=session-token'
            && ($query['filter']['email'] ?? null) === 'jane@example.com'
            && ($query['filter']['team'] ?? null) === 'ops';
    });
});
