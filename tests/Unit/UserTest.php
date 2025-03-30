<?php

namespace Cerberus\Tests\Unit;

use Cerberus\Cerberus;
use Cerberus\Resources\User;
use Cerberus\Tests\TestCase;
use Fetch\Interfaces\ClientHandler;
use Fetch\Interfaces\Response;
use Illuminate\Foundation\Testing\WithFaker;
use Mockery;

class UserTest extends TestCase
{
    use WithFaker;

    protected $http;

    protected Cerberus $cerberus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = Mockery::mock(ClientHandler::class);

        // Prevent real token lookup
        $this->http->shouldReceive('hasHeader')
            ->andReturn(true)
            ->byDefault();

        $this->http->shouldReceive('withToken')
            ->andReturnSelf()
            ->byDefault();

        $this->cerberus = new Cerberus($this->http);
    }

    /**
     * @test
     */
    public function fetch_all_users(): void
    {
        $payload = [
            [
                'id' => 1,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'super-admin@cerberus.io',
                'username' => 'Super-Admin',
                'organisation' => [
                    'name' => 'Cerberus IAM™',
                    'slug' => 'cerberus-iam',
                ],
            ],
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('ok')->andReturn(true);
        $response->shouldReceive('json')->andReturn(['data' => $payload]);

        $this->http->shouldReceive('withQueryParameters')
            ->with(Mockery::any())
            ->andReturn($this->http);

        $this->http->shouldReceive('get')
            ->once()
            ->with('/users')
            ->andReturn($response);

        $users = $this->cerberus->users()->get();

        $this->assertIsArray($users);
        $this->assertNotEmpty($users, 'Expected user list to be non-empty');
        $this->assertInstanceOf(User::class, $users[0]);
    }

    /**
     * @test
     */
    public function create_new_user(): void
    {
        $data = [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'username' => $this->faker->userName(),
            'email' => $this->faker->email(),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'organisation_id' => 1,
        ];

        $response = Mockery::mock(Response::class);
        $response->shouldReceive('ok')->andReturn(true);
        $response->shouldReceive('json')->andReturn($data);

        $this->http->shouldReceive('post')
            ->once()
            ->with('/users', $data)
            ->andReturn($response);

        $user = $this->cerberus->users()->create($data);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($data['first_name'], $user->first_name);
        $this->assertEquals($data['last_name'], $user->last_name);
        $this->assertEquals($data['email'], $user->email);
        $this->assertEquals($data['username'], $user->username);
    }
}
