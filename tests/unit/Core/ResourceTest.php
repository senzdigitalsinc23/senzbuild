<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Resource;
use App\Core\ResourceCollection;
use App\Core\Request;

class ResourceTest extends TestCase
{
    public function test_resource_transforms_data(): void
    {
        $resource = new TestUserResource(['id' => 1, 'name' => 'John', 'email' => 'john@test.com']);
        $result = $resource->resolve();

        $this->assertSame(['id' => 1, 'name' => 'John', 'email' => 'john@test.com'], $result);
    }

    public function test_sparse_fieldsets_filters_output(): void
    {
        $request = $this->createMock(\App\Core\Request::class);
        $request->method('getQuery')->with('fields')->willReturn('id,name');

        $resource = new TestUserResource(['id' => 1, 'name' => 'John', 'email' => 'john@test.com']);
        $resource->withRequest($request);
        $result = $resource->resolve();

        $this->assertSame(['id' => 1, 'name' => 'John'], $result);
        $this->assertArrayNotHasKey('email', $result);
    }

    public function test_resource_without_request_returns_all_fields(): void
    {
        $resource = new TestUserResource(['id' => 1, 'name' => 'John', 'email' => 'john@test.com']);
        $result = $resource->resolve();

        $this->assertCount(3, $result);
    }

    public function test_resource_collection_transforms_items(): void
    {
        $items = [
            ['id' => 1, 'name' => 'John', 'email' => 'john@test.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@test.com'],
        ];
        $collection = new TestUserCollection($items, TestUserResource::class);
        $result = $collection->resolve();

        $this->assertCount(2, $result);
        $this->assertSame(['id' => 1, 'name' => 'John', 'email' => 'john@test.com'], $result[0]);
        $this->assertSame(['id' => 2, 'name' => 'Jane', 'email' => 'jane@test.com'], $result[1]);
    }
}

class TestUserResource extends Resource
{
    protected array $fields = ['id', 'name', 'email'];

    public function toArray(): array
    {
        return [
            'id'    => $this->resource['id'],
            'name'  => $this->resource['name'],
            'email' => $this->resource['email'] ?? null,
        ];
    }
}

class TestUserCollection extends ResourceCollection {}
