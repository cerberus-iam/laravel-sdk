<?php

namespace Cerberus\Tests\Unit;

use Cerberus\Exceptions\ResourceDeleteException;
use Cerberus\Exceptions\ResourceNotFoundException;
use Cerberus\Exceptions\ResourceUpdateException;
use Cerberus\Resources\Resource;
use Cerberus\Resources\ResourceBuilder;
use Cerberus\Tests\Stubs\TestResource;
use Fetch\Interfaces\ClientHandler;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ResourceTest extends TestCase
{
    protected TestResource $resource;

    protected MockObject $mockClient;

    protected MockObject $mockResponse;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock response object
        $this->mockResponse = $this->createMock(Response::class);

        // Mock client handler
        $this->mockClient = $this->createMock(ClientHandler::class);

        // Create container instance and bind the mock client
        $container = new Container;
        Container::setInstance($container);
        $container->instance(ClientHandler::class, $this->mockClient);

        // Create a resource instance
        $this->resource = new TestResource;
        $this->resource->setConnection($this->mockClient);
    }

    public function test_constructor_sets_resource_name(): void
    {
        $this->assertEquals('test/resources', $this->resource->getResourceName());
    }

    public function test_make_creates_new_instance(): void
    {
        $resource = TestResource::make(['name' => 'Test User']);

        $this->assertInstanceOf(TestResource::class, $resource);
        $this->assertEquals('Test User', $resource->name);
    }

    public function test_query_returns_resource_builder(): void
    {
        $builder = TestResource::query();

        $this->assertInstanceOf(ResourceBuilder::class, $builder);
    }

    public function test_get_attribute_returns_value(): void
    {
        $resource = new TestResource(['name' => 'Test User']);

        $this->assertEquals('Test User', $resource->getAttribute('name'));
    }

    public function test_set_attribute_sets_value(): void
    {
        $this->resource->setAttribute('name', 'Test User');

        $this->assertEquals('Test User', $this->resource->getAttribute('name'));
    }

    public function test_fill_only_fills_fillable_attributes(): void
    {
        $this->resource->fill([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'not_fillable' => 'Value',
        ]);

        $this->assertEquals('Test User', $this->resource->name);
        $this->assertEquals('test@example.com', $this->resource->email);
        $this->assertNull($this->resource->not_fillable);
    }

    public function test_force_fill_sets_all_attributes(): void
    {
        $this->resource->forceFill([
            'name' => 'Test User',
            'not_fillable' => 'Value',
        ]);

        $this->assertEquals('Test User', $this->resource->name);
        $this->assertEquals('Value', $this->resource->not_fillable);
    }

    public function test_is_dirty_detects_changes(): void
    {
        $resource = new TestResource(['name' => 'Original', 'email' => 'nx@example.com']);
        $this->assertFalse($resource->isDirty());

        $resource->name = 'Updated';
        $this->assertTrue($resource->isDirty());
        $this->assertTrue($resource->isDirty('name'));
        $this->assertFalse($resource->isDirty('email'));
    }

    public function test_get_dirty_returns_changed_attributes(): void
    {
        $resource = new TestResource(['name' => 'Original', 'email' => 'test@example.com']);
        $resource->syncOriginal(); // Make sure to synchronize original values
        $resource->name = 'Updated';

        $dirty = $resource->getDirty();
        $this->assertCount(1, $dirty);
        $this->assertEquals('Updated', $dirty['name']);
    }

    public function test_sync_original_resets_tracking(): void
    {
        $resource = new TestResource(['name' => 'Original']);
        $resource->syncOriginal();
        $resource->name = 'Updated';
        $this->assertTrue($resource->isDirty());

        $resource->syncOriginal();
        $this->assertFalse($resource->isDirty());
    }

    public function test_to_array_excludes_hidden_fields(): void
    {
        $resource = new TestResource([
            'name' => 'Test User',
            'secret_field' => 'Secret Value',
        ]);

        $array = $resource->toArray();
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('secret_field', $array);
    }

    public function test_to_json_converts_to_json_string(): void
    {
        $resource = new TestResource(['name' => 'Test User']);

        $json = $resource->toJson();
        $this->assertIsString($json);
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertEquals('Test User', $decoded['name']);
    }

    public function test_array_access_implementation(): void
    {
        $resource = new TestResource;

        // offsetSet
        $resource['name'] = 'Test User';
        $this->assertEquals('Test User', $resource->name);

        // offsetExists
        $this->assertTrue(isset($resource['name']));
        $this->assertFalse(isset($resource['unknown']));

        // offsetGet
        $this->assertEquals('Test User', $resource['name']);

        // offsetUnset
        unset($resource['name']);
        $this->assertNull($resource->name);
    }

    public function test_save_creates_new_resource(): void
    {
        // Setup response mock
        $this->mockResponse->method('successful')->willReturn(true);
        $this->mockResponse->method('json')->willReturn([
            'id' => 123,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Setup client mock
        $this->mockClient->expects($this->once())
            ->method('post')
            ->with('/test/resources', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ])
            ->willReturn($this->mockResponse);

        // Create and save resource
        $resource = new TestResource([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $resource->setConnection($this->mockClient);

        $result = $resource->save();

        $this->assertTrue($result);
        $this->assertTrue($resource->exists);
        $this->assertEquals(123, $resource->id);
    }

    public function test_save_updates_existing_resource(): void
    {
        // Setup initial resource state with a primary key
        $resource = new TestResource([
            'id' => 123,
            'name' => 'Original Name',
            'email' => 'test@example.com',
        ]);
        $resource->exists = true;
        $resource->syncOriginal();
        $resource->setConnection($this->mockClient);

        // Update an attribute
        $resource->name = 'Updated Name';

        // Setup response mock
        $this->mockResponse->method('successful')->willReturn(true);
        $this->mockResponse->method('json')->willReturn([
            'id' => 123,
            'name' => 'Updated Name',
            'email' => 'test@example.com',
        ]);

        // Setup client mock
        $this->mockClient->expects($this->once())
            ->method('put')
            ->with('/test/resources/123', ['name' => 'Updated Name'])
            ->willReturn($this->mockResponse);

        $result = $resource->save();

        $this->assertTrue($result);
        $this->assertEquals('Updated Name', $resource->name);
    }

    public function test_update_fills_and_saves_resource(): void
    {
        // Setup initial resource state with a primary key
        $resource = new TestResource([
            'id' => 123,
            'name' => 'Original Name',
        ]);
        $resource->exists = true;
        $resource->syncOriginal();
        $resource->setConnection($this->mockClient);

        // Setup response mock
        $this->mockResponse->method('successful')->willReturn(true);
        $this->mockResponse->method('json')->willReturn([
            'id' => 123,
            'name' => 'Updated Name',
            'status' => 'active',
        ]);

        // Setup client mock
        $this->mockClient->expects($this->once())
            ->method('put')
            ->with('/test/resources/123', ['name' => 'Updated Name', 'status' => 'active'])
            ->willReturn($this->mockResponse);

        $result = $resource->update([
            'name' => 'Updated Name',
            'status' => 'active',
        ]);

        $this->assertTrue($result);
        $this->assertEquals('Updated Name', $resource->name);
        $this->assertEquals('active', $resource->status);
    }

    public function test_update_fails_for_non_existing_resource(): void
    {
        $resource = new TestResource(['name' => 'Test User']);
        $resource->exists = false;

        $this->expectException(ResourceUpdateException::class);
        $resource->update(['name' => 'Updated Name']);
    }

    public function test_delete_removes_resource(): void
    {
        // Setup initial resource state with a primary key
        $resource = new TestResource([
            'id' => 123,
            'name' => 'Test User',
        ]);
        $resource->exists = true;
        $resource->setConnection($this->mockClient);

        // Setup response mock
        $this->mockResponse->method('successful')->willReturn(true);
        $this->mockResponse->method('status')->willReturn(200);

        // Setup client mock
        $this->mockClient->expects($this->once())
            ->method('delete')
            ->with('/test/resources/123')
            ->willReturn($this->mockResponse);

        $result = $resource->delete();

        $this->assertTrue($result);
        $this->assertFalse($resource->exists);
    }

    public function test_delete_fails_with_error_response(): void
    {
        // Setup initial resource state with a primary key
        $resource = new TestResource([
            'id' => 123,
            'name' => 'Test User',
        ]);
        $resource->exists = true;
        $resource->setConnection($this->mockClient);

        // Setup response mock
        $this->mockResponse->method('successful')->willReturn(false);
        $this->mockResponse->method('status')->willReturn(404);

        // Setup client mock
        $this->mockClient->expects($this->once())
            ->method('delete')
            ->with('/test/resources/123')
            ->willReturn($this->mockResponse);

        $this->expectException(ResourceDeleteException::class);
        $resource->delete();
    }

    public function test_delete_without_primary_key_fails(): void
    {
        $resource = new TestResource(['name' => 'Test User']);
        $resource->exists = true;

        $this->expectException(ResourceDeleteException::class);
        $resource->delete();
    }

    public function test_find_returns_resource_by_id(): void
    {
        // Mock response
        $this->mockResponse->method('successful')->willReturn(true);
        $this->mockResponse->method('json')->willReturn([
            'id' => 123,
            'name' => 'Test User',
        ]);

        // Mock client
        $this->mockClient->expects($this->once())
            ->method('get')
            ->with('/test/resources/123')
            ->willReturn($this->mockResponse);

        // Call find
        $resource = TestResource::find(123);

        $this->assertInstanceOf(TestResource::class, $resource);
        $this->assertEquals(123, $resource->id);
        $this->assertEquals('Test User', $resource->name);
    }

    public function test_find_or_fail_throws_exception_when_not_found(): void
    {
        // Mock response
        $this->mockResponse->method('successful')->willReturn(false);
        $this->mockResponse->method('status')->willReturn(404);

        // Mock client
        $this->mockClient->expects($this->once())
            ->method('get')
            ->with('/test/resources/123')
            ->willReturn($this->mockResponse);

        $this->expectException(ResourceNotFoundException::class);
        TestResource::findOrFail(123);
    }

    public function test_create_static_method_creates_resource(): void
    {
        // Mock response
        $this->mockResponse->method('successful')->willReturn(true);
        $this->mockResponse->method('json')->willReturn([
            'id' => 123,
            'name' => 'Test User',
        ]);

        // Mock client
        $this->mockClient->expects($this->once())
            ->method('post')
            ->with('/test/resources', ['name' => 'Test User'])
            ->willReturn($this->mockResponse);

        // Call create
        $resource = TestResource::create(['name' => 'Test User']);

        $this->assertInstanceOf(TestResource::class, $resource);
        $this->assertEquals(123, $resource->id);
        $this->assertEquals('Test User', $resource->name);
    }

    public function test_destroy_static_method_deletes_resources(): void
    {
        // Create mock models with delete + getKey stubs
        $mockModel1 = $this->createPartialMock(TestResource::class, ['delete', 'getKey']);
        $mockModel1->exists = true;
        $mockModel1->expects($this->once())->method('delete')->willReturn(true);
        $mockModel1->method('getKey')->willReturn(123);

        $mockModel2 = $this->createPartialMock(TestResource::class, ['delete', 'getKey']);
        $mockModel2->exists = true;
        $mockModel2->expects($this->once())->method('delete')->willReturn(true);
        $mockModel2->method('getKey')->willReturn(456);

        // Replace static::find() logic — here, we're going to override behavior manually
        // by creating an inline stub class or refactor for easier testing
        $stub = new class([$mockModel1, $mockModel2]) extends TestResource
        {
            private static array $lookup = [];

            public function __construct(array $map)
            {
                parent::__construct();
                self::$lookup = [
                    123 => $map[0],
                    456 => $map[1],
                ];
            }

            public static function find($id): mixed
            {
                return self::$lookup[$id] ?? null;
            }
        };

        // Manually call destroy on the stub class
        $deletedCount = $stub::destroy([123, 456]);

        // Assert success
        $this->assertEquals(2, $deletedCount);
    }

    public function test_destroy_static_method_handles_partial_failures(): void
    {
        // Create mock models with delete + getKey stubs

        // First one succeeds
        $mockModel1 = $this->createPartialMock(TestResource::class, ['delete', 'getKey']);
        $mockModel1->exists = true;
        $mockModel1->expects($this->once())->method('delete')->willReturn(true);
        $mockModel1->method('getKey')->willReturn(123);

        // Second one fails
        $mockModel2 = $this->createPartialMock(TestResource::class, ['delete', 'getKey']);
        $mockModel2->exists = true;
        $mockModel2->expects($this->once())->method('delete')->willReturn(false);
        $mockModel2->method('getKey')->willReturn(456);

        // Inline stub that overrides static find() method
        $stub = new class([$mockModel1, $mockModel2]) extends TestResource
        {
            private static array $lookup = [];

            public function __construct(array $map)
            {
                parent::__construct();
                self::$lookup = [
                    123 => $map[0],
                    456 => $map[1],
                ];
            }

            public static function find($id): mixed
            {
                return self::$lookup[$id] ?? null;
            }
        };

        // Run destroy with partial failure
        $deletedCount = $stub::destroy([123, 456]);

        // Expect only the first delete to succeed
        $this->assertEquals(1, $deletedCount);
    }

    public function test_magic_methods_get_and_set(): void
    {
        $resource = new TestResource;

        // __set
        $resource->name = 'Test User';

        // __get
        $this->assertEquals('Test User', $resource->name);

        // __isset
        $this->assertTrue(isset($resource->name));
        $this->assertFalse(isset($resource->unknown));

        // __unset
        unset($resource->name);
        $this->assertNull($resource->name);
    }

    public function test_to_string_returns_json(): void
    {
        $resource = new TestResource(['name' => 'Test User']);

        $string = (string) $resource;
        $this->assertIsString($string);
        $this->assertJson($string);
    }
}
