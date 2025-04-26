<?php

namespace Cerberus\Tests\Unit;

use Cerberus\Resources\Resource;
use Cerberus\Resources\ResourceBuilder;
use Cerberus\Tests\TestCase;
use Mockery;
use ReflectionClass;

class ResourceBuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_select_sets_columns()
    {
        $resource = Mockery::mock(Resource::class);
        $builder = new ResourceBuilder($resource);

        $builder->select(['id', 'name']);
        $this->assertEquals(['id', 'name'], $this->getPrivateProperty($builder, 'columns'));
    }

    public function test_where_with_single_condition()
    {
        $resource = Mockery::mock(Resource::class);
        $builder = new ResourceBuilder($resource);

        $builder->where('status', 'active');
        $wheres = $this->getPrivateProperty($builder, 'wheres');

        $this->assertArrayHasKey('status', $wheres);
        $this->assertEquals(['operator' => '=', 'value' => 'active'], $wheres['status']);
    }

    public function test_where_in_stores_values()
    {
        $resource = Mockery::mock(Resource::class);
        $builder = new ResourceBuilder($resource);

        $builder->whereIn('id', [1, 2, 3]);
        $wheres = $this->getPrivateProperty($builder, 'wheres');

        $this->assertArrayHasKey('id', $wheres);
        $this->assertEquals(['operator' => 'in', 'value' => [1, 2, 3]], $wheres['id']);
    }

    public function test_order_by_stores_sorting()
    {
        $resource = Mockery::mock(Resource::class);
        $builder = new ResourceBuilder($resource);

        $builder->orderBy('name', 'desc');
        $orders = $this->getPrivateProperty($builder, 'orders');

        $this->assertEquals(['name' => 'desc'], $orders);
    }

    public function test_limit_is_capped_at_minimum_of_one()
    {
        $resource = Mockery::mock(Resource::class);
        $builder = new ResourceBuilder($resource);

        $builder->limit(0);
        $this->assertEquals(1, $this->getPrivateProperty($builder, 'limit'));
    }

    public function test_offset_is_not_negative()
    {
        $resource = Mockery::mock(Resource::class);
        $builder = new ResourceBuilder($resource);

        $builder->offset(-10);
        $this->assertEquals(0, $this->getPrivateProperty($builder, 'offset'));
    }

    protected function getPrivateProperty($object, string $property)
    {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue($object);
    }
}
