<?php

namespace Cerberus\Tests\Unit;

use Cerberus\Resources\Role;
use Cerberus\Resources\User;
use Cerberus\Tests\TestCase;
use Fetch\Interfaces\ClientHandler;
use Fetch\Interfaces\Response;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate;
use Mockery;

class UserTest extends TestCase
{
    protected ClientHandler $http;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = Mockery::mock(ClientHandler::class);
        $this->user = new User([
            'uid' => 123,
            'email' => 'test@example.com',
            'username' => 'testuser',
        ]);
        $this->user->setConnection($this->http);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_set_and_get_access_token(): void
    {
        $this->user->withToken('abc123');
        $this->assertSame('abc123', $this->user->getAccessToken());
    }

    public function test_assign_role(): void
    {
        $role = new class extends Role
        {
            public string $name = 'admin';
        };

        $this->http->shouldReceive('post')
            ->once()
            ->with('/users/123/roles', ['roles' => ['admin']]);

        $result = $this->user->assignRole($role);
        $this->assertSame($this->user, $result);
    }

    public function test_has_roles(): void
    {
        $this->http->shouldReceive('get')
            ->once()
            ->with('/users/123/roles')
            ->andReturn(Mockery::mock(Response::class, [
                'json' => [['name' => 'admin']],
            ]));
        $this->http->shouldReceive('get')
            ->once()
            ->with('/users/123/roles')
            ->andReturn(Mockery::mock(Response::class, [
                'json' => [],
            ]));

        $this->assertTrue($this->user->hasRoles(['admin']));
        $this->assertFalse($this->user->hasRoles(['user']));
    }

    public function test_can_check_abilities(): void
    {
        $mockGate = Mockery::mock(Gate::class);
        $mockGate->shouldReceive('forUser')->with($this->user)->andReturnSelf();
        $mockGate->shouldReceive('check')->with('edit-posts', [])->andReturn(true);
        $mockGate->shouldReceive('any')->with(['edit-posts', 'delete-posts'], [])->andReturn(true);

        Container::setInstance(Mockery::mock(Container::class, function ($mock) use ($mockGate) {
            $mock->shouldReceive('make')->with(Gate::class)->andReturn($mockGate);
        }));

        $this->assertTrue($this->user->can('edit-posts'));
        $this->assertTrue($this->user->canAny(['edit-posts', 'delete-posts']));
        $this->assertFalse($this->user->cant('edit-posts'));
        $this->assertFalse($this->user->cannot('edit-posts'));
    }

    public function test_notify_throws_exception(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Notifications are not supported in Cerberus SDK yet.');

        $this->user->notify('some-notification');
    }
}
