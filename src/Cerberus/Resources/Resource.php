<?php

namespace Cerberus\Resources;

use ArrayAccess;
use Fetch\Interfaces\ClientHandler;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;

/**
 * Abstract class representing a resource.
 * Provides methods for interacting with API resources.
 */
abstract class Resource implements Arrayable, ArrayAccess
{
    /**
     * The resource name.
     */
    protected string $resource;

    /**
     * The filters applied to the resource.
     */
    protected array $filters = [];

    /**
     * The attributes of the resource.
     */
    protected array $attributes = [];

    /**
     * The original attributes of the resource.
     */
    protected array $original = [];

    /**
     * The primary key of the resource.
     */
    protected string $primaryKey = 'uid';

    /**
     * The parameters for the resource.
     */
    protected array $parameters = [];

    /**
     * Create new instance of the resource.
     *
     * @return void
     */
    public function __construct(protected ClientHandler $connection, array $attributes = [])
    {
        $this->resource = $this->resource ?? Str::plural(Str::snake(class_basename($this)));
        $this->fill($attributes);
        $this->original = $this->attributes;
    }

    /**
     * Forcefully fill the resource attributes.
     *
     * @param  array<string, mixed>  $attributes  Attributes to fill.
     */
    public function forceFill(array $attributes): static
    {
        return $this->fill($attributes); // no guard logic yet
    }

    /**
     * Fill the resource attributes.
     *
     * @param  array  $attributes  Attributes to fill.
     */
    public function fill(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * Get the primary key value of the resource.
     */
    public function getKey(): mixed
    {
        return $this->attributes['uid'] ?? null;
    }

    /**
     * Create a new instance of the resource.
     *
     * @param  ClientHandler  $connection  The client connection handler.
     */
    public static function make(ClientHandler $connection): static
    {
        return new static($connection);
    }

    /**
     * Add a filter condition to the resource query.
     *
     * @param  string  $key  The filter key.
     * @param  mixed  $value  The filter value.
     */
    public function where(string $key, mixed $value): static
    {
        $this->filters[$key] = $value;

        return $this;
    }

    /**
     * Add a "where in" filter condition to the resource query.
     *
     * @param  string  $key  The filter key.
     * @param  array  $values  The filter values.
     */
    public function whereIn(string $key, array $values): static
    {
        $this->filters[$key] = ['in' => $values];

        return $this;
    }

    /**
     * Retrieve all resources matching the filters.
     */
    public function get(): array
    {
        $response = $this->connection
            ->withQueryParameters($this->filters)
            ->get("/{$this->resource}");

        return collect($response->json()['data'] ?? [])
            ->map(fn ($item) => new static($this->connection, $item))
            ->all();
    }

    /**
     * Retrieve the first resource matching the filters.
     */
    public function first(): ?static
    {
        return $this->get()[0] ?? null;
    }

    /**
     * Find a resource by its ID.
     *
     * @param  string  $id  The resource ID.
     */
    public function find(string $id): ?static
    {
        $response = $this->connection->get("/{$this->resource}/{$id}");

        return isset($response->json()['data']) ? new static($this->connection, $response->json()['data']) : null;
    }

    /**
     * Create a new resource.
     *
     * @param  array  $data  The data for the new resource.
     */
    public function create(array $data): static
    {
        $response = $this->connection->post("/{$this->resource}", $data);

        return new static($this->connection, $response->json());
    }

    /**
     * Update the resource with new data.
     *
     * @param  array  $data  The data to update the resource with.
     */
    public function update(array $data): static
    {
        $id = $this->getKey();
        $response = $this->connection->put("/{$this->resource}/{$id}", $data);

        return new static($this->connection, $response->json());
    }

    /**
     * Delete the resource.
     */
    public function delete(): bool
    {
        $id = $this->getKey();
        $this->connection->delete("/{$this->resource}/{$id}");

        return true;
    }

    /**
     * Check if the resource exists (has a primary key).
     */
    public function exists(): bool
    {
        return isset($this->attributes['uid']);
    }

    /**
     * Save the resource (create or update).
     */
    public function save(): static
    {
        return $this->exists() ? $this->update($this->attributes) : $this->create($this->attributes);
    }

    /**
     * Get the primary key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Convert the resource to an array.
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Magic getter for resource attributes.
     *
     * @param  string  $key  The attribute key.
     * @return mixed
     */
    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Magic setter for resource attributes.
     *
     * @param  string  $key  The attribute key.
     * @param  mixed  $value  The attribute value.
     */
    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Magic isset for resource attributes.
     *
     * @param  string  $key  The attribute key.
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Check if an offset exists in the attributes.
     *
     * @param  mixed  $offset  The offset key.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Get an offset value from the attributes.
     *
     * @param  mixed  $offset  The offset key.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    /**
     * Set an offset value in the attributes.
     *
     * @param  mixed  $offset  The offset key.
     * @param  mixed  $value  The value to set.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->attributes[$offset] = $value;
    }

    /**
     * Unset an offset in the attributes.
     *
     * @param  mixed  $offset  The offset key.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Get the client connection handler.
     */
    public function getConnection(): ClientHandler
    {
        return $this->connection;
    }
}
