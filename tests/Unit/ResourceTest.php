<?php

namespace Tests\Unit\Resources;

use Cerberus\Tests\Stubs\TestResource;
use Fetch\Interfaces\ClientHandler;
use Illuminate\Http\Client\Response;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ResourceTest extends TestCase
{
    protected ClientHandler|MockInterface $connection;

    protected TestResource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = m::mock(ClientHandler::class);
        $this->resource = new TestResource($this->connection);
    }

    protected function tearDown(): void
    {
        m::close();

        parent::tearDown();
    }

    public function test_make_creates_new_instance()
    {
        $resource = TestResource::make($this->connection);

        $this->assertInstanceOf(TestResource::class, $resource);
    }

    public function test_get_attribute()
    {
        $resource = new TestResource($this->connection, ['name' => 'Test']);

        $this->assertEquals('Test', $resource->getAttribute('name'));
        $this->assertNull($resource->getAttribute('nonexistent'));
    }

    public function test_fill_and_force_fill()
    {
        $resource = new TestResource($this->connection);

        $resource->fill(['name' => 'Test']);
        $this->assertEquals('Test', $resource->getAttribute('name'));

        $resource->forceFill(['description' => 'Description']);
        $this->assertEquals('Description', $resource->getAttribute('description'));
    }

    public function test_get_key()
    {
        $resource = new TestResource($this->connection, ['uid' => '123']);

        $this->assertEquals('123', $resource->getKey());
    }

    public function test_where_adds_filter()
    {
        $response = m::mock(Response::class);
        $response->shouldReceive('json')->andReturn(['data' => [['name' => 'Test']]]);

        $this->connection->shouldReceive('withQueryParameters')
            ->with(['name' => 'Test'])
            ->andReturnSelf();

        $this->connection->shouldReceive('get')
            ->with('/test_resources')
            ->andReturn($response);

        $results = $this->resource->where('name', 'Test')->get();

        $this->assertIsArray($results);
        $this->assertCount(1, $results);
        $this->assertInstanceOf(TestResource::class, $results[0]);
        $this->assertEquals('Test', $results[0]->getAttribute('name'));
    }

    public function test_where_in_adds_filter()
    {
        $response = m::mock(Response::class);
        $response->shouldReceive('json')->andReturn(['data' => [['name' => 'Test1'], ['name' => 'Test2']]]);

        $this->connection->shouldReceive('withQueryParameters')
            ->with(['name' => ['in' => ['Test1', 'Test2']]])
            ->andReturnSelf();

        $this->connection->shouldReceive('get')
            ->with('/test_resources')
            ->andReturn($response);

        $results = $this->resource->whereIn('name', ['Test1', 'Test2'])->get();

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertEquals('Test1', $results[0]->getAttribute('name'));
        $this->assertEquals('Test2', $results[1]->getAttribute('name'));
    }

    public function test_get_returns_collection_of_resources()
    {
        $response = m::mock(Response::class);
        $response->shouldReceive('json')->andReturn([
            'data' => [
                ['name' => 'Test1'],
                ['name' => 'Test2'],
            ],
        ]);

        $this->connection->shouldReceive('withQueryParameters')
            ->with([])
            ->andReturnSelf();

        $this->connection->shouldReceive('get')
            ->with('/test_resources')
            ->andReturn($response);

        $results = $this->resource->get();

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertInstanceOf(TestResource::class, $results[0]);
        $this->assertInstanceOf(TestResource::class, $results[1]);
    }

    public function test_first_returns_first_resource()
    {
        $response = m::mock(Response::class);
        $response->shouldReceive('json')->andReturn([
            'data' => [
                ['name' => 'Test1'],
                ['name' => 'Test2'],
            ],
        ]);

        $this->connection->shouldReceive('withQueryParameters')
            ->with([])
            ->andReturnSelf();

        $this->connection->shouldReceive('get')
            ->with('/test_resources')
            ->andReturn($response);

        $result = $this->resource->first();

        $this->assertInstanceOf(TestResource::class, $result);
        $this->assertEquals('Test1', $result->getAttribute('name'));
    }

    public function test_first_returns_null_when_no_results()
    {
        $response = m::mock(Response::class);
        $response->shouldReceive('json')->andReturn(['data' => []]);

        $this->connection->shouldReceive('withQueryParameters')
            ->andReturnSelf();

        $this->connection->shouldReceive('get')
            ->andReturn($response);

        $result = $this->resource->first();

        $this->assertNull($result);
    }

    public function test_find_returns_resource_by_id()
    {
        $response = m::mock(Response::class);
        $response->shouldReceive('json')->andReturn([
            'data' => ['uid' => '123', 'name' => 'Test'],
        ]);

        $this->connection->shouldReceive('get')
            ->with('/test_resources/123')
            ->andReturn($response);

        $result = $this->resource->find('123');

        $this->assertInstanceOf(TestResource::class, $result);
        $this->assertEquals('123', $result->getKey());
        $this->assertEquals('Test', $result->getAttribute('name'));
    }

    public function test_find_returns_null_when_not_found()
    {
        $response = m::mock(Response::class);
        $response->shouldReceive('json')->andReturn([]);

        $this->connection->shouldReceive('get')
            ->with('/test_resources/123')
            ->andReturn($response);

        $result = $this->resource->find('123');

        $this->assertNull($result);
    }

    public function test_create_resource()
    {
        $data = ['name' => 'New Resource'];
        $responseData = ['uid' => '123', 'name' => 'New Resource'];

        $response = m::mock(Response::class);
        $response->shouldReceive('json')->andReturn($responseData);

        $this->connection->shouldReceive('post')
            ->with('/test_resources', $data)
            ->andReturn($response);

        $result = $this->resource->create($data);

        $this->assertInstanceOf(TestResource::class, $result);
        $this->assertEquals('123', $result->getKey());
        $this->assertEquals('New Resource', $result->getAttribute('name'));
    }

    public function test_update_resource()
    {
        $resource = new TestResource($this->connection, ['uid' => '123', 'name' => 'Old Name']);
        $data = ['name' => 'Updated Name'];
        $responseData = ['uid' => '123', 'name' => 'Updated Name'];

        $response = m::mock(Response::class);
        $response->shouldReceive('json')->andReturn($responseData);

        $this->connection->shouldReceive('put')
            ->with('/test_resources/123', $data)
            ->andReturn($response);

        $result = $resource->update($data);

        $this->assertInstanceOf(TestResource::class, $result);
        $this->assertEquals('123', $result->getKey());
        $this->assertEquals('Updated Name', $result->getAttribute('name'));
    }

    public function test_delete_resource()
    {
        $resource = new TestResource($this->connection, ['uid' => '123']);

        $this->connection->shouldReceive('delete')
            ->with('/test_resources/123')
            ->once();

        $result = $resource->delete();

        $this->assertTrue($result);
    }

    public function test_exists_check()
    {
        $resource = new TestResource($this->connection);
        $this->assertFalse($resource->exists());

        $resource = new TestResource($this->connection, ['uid' => '123']);
        $this->assertFalse($resource->exists());

        $resource->markAsExists();
        $this->assertTrue($resource->exists());
    }

    public function test_save_creates_new_resource_when_not_exists()
    {
        $resource = new TestResource($this->connection);
        $resource->fill(['name' => 'New Resource']);

        $responseData = ['uid' => '123', 'name' => 'New Resource'];
        $response = m::mock(Response::class);
        $response->shouldReceive('json')->andReturn($responseData);

        $this->connection->shouldReceive('post')
            ->with('/test_resources', ['name' => 'New Resource'])
            ->andReturn($response);

        $result = $resource->save();

        $this->assertInstanceOf(TestResource::class, $result);
        $this->assertEquals('123', $result->getKey());
    }

    public function test_save_updates_only_dirty_attributes()
    {
        $originalData = ['uid' => '123', 'name' => 'Old Name'];
        $updatedData = ['name' => 'Updated Name'];
        $responseData = ['uid' => '123', 'name' => 'Updated Name'];

        $resource = new TestResource($this->connection, $originalData);
        $resource->exists = true;

        // Mutate the name
        $resource->name = 'Updated Name';

        $response = m::mock(Response::class);
        $response->shouldReceive('json')->andReturn($responseData);

        $this->connection->shouldReceive('put')
            ->with('/test_resources/123', $updatedData)
            ->once()
            ->andReturn($response);

        $result = $resource->save();

        $this->assertInstanceOf(TestResource::class, $result);
        $this->assertEquals('Updated Name', $result->getAttribute('name'));
    }

    public function test_to_array()
    {
        $attributes = ['uid' => '123', 'name' => 'Test'];
        $resource = new TestResource($this->connection, $attributes);

        $this->assertEquals($attributes, $resource->toArray());
    }

    public function test_array_access()
    {
        $resource = new TestResource($this->connection, ['uid' => '123', 'name' => 'Test']);

        // offsetExists
        $this->assertTrue(isset($resource['name']));
        $this->assertFalse(isset($resource['nonexistent']));

        // offsetGet
        $this->assertEquals('123', $resource['uid']);
        $this->assertEquals('Test', $resource['name']);
        $this->assertNull($resource['nonexistent']);

        // offsetSet
        $resource['description'] = 'Description';
        $this->assertEquals('Description', $resource['description']);

        // offsetUnset
        unset($resource['description']);
        $this->assertNull($resource['description']);
    }

    public function test_magic_methods()
    {
        $resource = new TestResource($this->connection, ['uid' => '123', 'name' => 'Test']);

        // __get
        $this->assertEquals('123', $resource->uid);
        $this->assertEquals('Test', $resource->name);
        $this->assertNull($resource->nonexistent);

        // __set
        $resource->description = 'Description';
        $this->assertEquals('Description', $resource->description);

        // __isset
        $this->assertTrue(isset($resource->name));
        $this->assertFalse(isset($resource->nonexistent));
    }

    public function test_get_connection()
    {
        $resource = new TestResource($this->connection);

        $this->assertSame($this->connection, $resource->getConnection());
    }

    public function test_get_key_name()
    {
        $resource = new TestResource($this->connection);

        $this->assertEquals('uid', $resource->getKeyName());
    }
}
