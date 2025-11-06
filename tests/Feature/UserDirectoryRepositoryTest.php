<?php

namespace CerberusIAM\Tests\Feature;

use CerberusIAM\Contracts\IamClient;
use CerberusIAM\Repositories\UserDirectoryRepository;
use CerberusIAM\Tests\Fixtures\FakeIamClient;
use CerberusIAM\Tests\Support\FetchStub;
use Illuminate\Http\Request;

class RepositoryStubResponse
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

it('builds requests with filter parameters', function () {
    FetchStub::$requests = [];
    FetchStub::$queue = [
        new RepositoryStubResponse(true, [
            'data' => [],
        ]),
    ];

    $fake = new FakeIamClient;
    app()->instance(IamClient::class, $fake);

    $repository = new UserDirectoryRepository($fake);

    $request = Request::create('/users', 'GET', [
        'email' => 'jane@example.com',
        'team' => 'ops',
    ]);

    $repository->list('acme', $request, ['page' => 2], 'access-token', 'session-token');

    expect(FetchStub::$requests)->toHaveCount(1);

    $requestPayload = FetchStub::$requests[0];
    expect($requestPayload['url'])->toBe('https://cerberus.test/v1/admin/users');

    $headers = $requestPayload['options']['headers'];
    expect($headers['X-Org-Domain'])->toBe('acme');
    expect($headers['Authorization'])->toBe('Bearer access-token');
    expect($headers['Cookie'])->toBe('cerb_sid=session-token');

    $query = $requestPayload['options']['query'];
    expect($query['filter[email]'])->toBe('jane@example.com');
    expect($query['filter[team]'])->toBe('ops');
    expect($query['page'])->toBe(2);
});
