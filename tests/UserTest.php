<?php

namespace Cerberus\Tests;

use Cerberus\Cerberus;
use Cerberus\Resources\User;
use Fetch\Http\ClientHandler;
use Fetch\Interfaces\ClientHandler as ClientHandlerInterface;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;

class UserTest extends TestCase
{
    use WithFaker;

    /**
     * The Cerberus API client instance.
     *
     * @var \Cerberus\Cerberus
     */
    protected $query;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = $this->createConnection();
        $this->query = new Cerberus($connection);
        $this->query->auth()->login([
            'email' => 'super-admin@cerberus.io',
            'password' => 'password',
        ]);
        $this->query->testing();

        $request = Request::create('/', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => $this->query->auth()->getToken(),
        ]);

        $this->app->instance('request', $request);
    }

    public function test_fetch_all_users(): void
    {
        $query = $this->query->users()->get();

        // Assert the response is an array
        $this->assertIsArray($query);

        // Assert the array is not empty
        $this->assertNotEmpty($query);

        // Assert the first element is an instance of Cerberus\Resources\User
        $this->assertInstanceOf(User::class, $query[0]);

        // Assert specific attributes of the first user
        $user = $query[0];
        $this->assertEquals('John', $user->first_name);
        $this->assertEquals('Doe', $user->last_name);
        $this->assertEquals('super-admin@cerberus.io', $user->email);
        $this->assertEquals('Super-Admin', $user->username);

        // Assert organisation details
        $this->assertIsArray($user->organisation);
        $this->assertEquals('Cerberus IAM™', $user->organisation['name']);
        $this->assertEquals('cerberus-iam', $user->organisation['slug']);
    }

    public function test_create_new_user(): void
    {
        $query = $this->query->users()->create($data = [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'username' => $this->faker->userName(),
            'email' => $this->faker->email(),
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'organisation_id',
        ]);

        // Assert the first element is an instance of Cerberus\Resources\User
        $this->assertInstanceOf(User::class, $query);

        // Assert specific attributes of the first user
        $this->assertEquals($data['first_name'], $query->first_name);
        $this->assertEquals($data['last_name'], $query->last_name);
        $this->assertEquals($data['email'], $query->email);
        $this->assertEquals($data['username'], $query->username);
    }

    /**
     * Create a new connection to the Cerberus API.
     */
    protected function createConnection(): ClientHandlerInterface
    {
        return new ClientHandler(null, [
            'base_uri' => rtrim(sprintf(
                '%s/%s',
                Cerberus::API_URI,
                Cerberus::API_VERSION
            ), '/'),

            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                Cerberus::API_KEY_NAME => env('CERBERUS_API_KEY'),
                Cerberus::API_SECRET_NAME => env('CERBERUS_API_SECRET'),
            ],
        ]);
    }
}
