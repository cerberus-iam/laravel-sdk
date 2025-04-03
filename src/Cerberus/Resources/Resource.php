<?php

namespace Cerberus\Resources;

use ArrayAccess;
use Fetch\Interfaces\ClientHandler;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Support\Str;
use JsonSerializable;
use Stringable;
use Symfony\Component\HttpFoundation\Exception\JsonException;

/**
 * @mixin \Illuminate\Support\Traits\ForwardsCalls
 */
abstract class Resource implements Arrayable, ArrayAccess, Jsonable, JsonSerializable, Stringable
{
    /**
     * Indicates if the resource exists.
     */
    public bool $exists = false;

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
     * Indicates if string output should be escaped.
     */
    protected bool $escapeWhenCastingToString = false;

    /**
     * Create a new resource instance.
     */
    public function __construct(protected ClientHandler $connection, array $attributes = [])
    {
        $this->resource = $this->resource ?? Str::plural(Str::snake(class_basename($this)));
        $this->fill($attributes);
        $this->syncOriginal();
    }

    /**
     * Create a new instance.
     */
    public static function make(ClientHandler $connection, array $attributes = []): static
    {
        return new static($connection, $attributes);
    }

    /**
     * Get an attribute.
     */
    public function getAttribute(string $attribute): mixed
    {
        return $this->attributes[$attribute] ?? null;
    }

    /**
     * Set an attribute.
     */
    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Fill attributes bypassing protection.
     */
    public function forceFill(array $attributes): static
    {
        return $this->fill($attributes);
    }

    /**
     * Fill attributes.
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Get the primary key value.
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Get the primary key name.
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Add a filter condition.
     */
    public function where(string $key, mixed $value): static
    {
        $this->filters[$key] = $value;

        return $this;
    }

    /**
     * Add a where-in filter condition.
     */
    public function whereIn(string $key, array $values): static
    {
        $this->filters[$key] = ['in' => $values];

        return $this;
    }

    /**
     * Retrieve all matching resources.
     */
    public function get(): array
    {
        $response = $this->connection
            ->withQueryParameters($this->filters)
            ->get("/{$this->resource}");

        return collect($response->json()['data'] ?? [])
            ->map(fn ($item) => (new static($this->connection, $item))->markAsExists())
            ->all();
    }

    /**
     * Retrieve the first matching resource.
     */
    public function first(): ?static
    {
        return value($this->get()[0] ?? null)?->markAsExists();
    }

    /**
     * Find a resource by ID.
     */
    public function find(string $id): ?static
    {
        $response = $this->connection->get("/{$this->resource}/{$id}")->json();
        $data = $response['data'] ?? $response;

        return blank($data) ? null : (new static($this->connection, $data))->markAsExists();
    }

    /**
     * Create the resource.
     */
    public function create(array $data): static
    {
        $response = $this->connection->post("/{$this->resource}", $data);

        return (new static($this->connection, $response->json()))->markAsExists();
    }

    /**
     * Update the resource.
     */
    public function update(array $data): static
    {
        $response = $this->connection->put("/{$this->resource}/{$this->getKey()}", $data);

        return (new static($this->connection, $response->json()))->markAsExists();
    }

    /**
     * Delete the resource.
     */
    public function delete(): bool
    {
        $this->connection->delete("/{$this->resource}/{$this->getKey()}");
        $this->exists = false;

        return true;
    }

    /**
     * Check if the resource exists.
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Save the resource.
     */
    public function save(): static
    {
        if ($this->exists()) {
            $dirty = $this->getDirty();

            return blank($dirty) ? $this : $this->update($dirty);
        }

        return $this->create($this->attributes);
    }

    /**
     * Determine if attributes are dirty.
     */
    public function isDirty(null|string|array $attributes = null): bool
    {
        return count($this->getDirty($attributes)) > 0;
    }

    /**
     * Get dirty attributes.
     */
    public function getDirty(null|string|array $attributes = null): array
    {
        $dirty = [];
        $attributes = is_null($attributes) ? array_keys($this->attributes) : (array) $attributes;

        foreach ($attributes as $key) {
            if (! array_key_exists($key, $this->original) || $this->attributes[$key] !== $this->original[$key]) {
                $dirty[$key] = $this->attributes[$key] ?? null;
            }
        }

        return $dirty;
    }

    /**
     * Sync original attributes with current.
     */
    public function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    /**
     * Mark the model as existing.
     */
    public function markAsExists(): static
    {
        $this->exists = true;
        $this->syncOriginal();

        return $this;
    }

    /**
     * Convert the resource to array.
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Convert the resource to JSON.
     *
     * @throws \Illuminate\Database\Eloquent\JsonEncodingException
     */
    public function toJson($options = 0): string
    {
        try {
            return json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw JsonEncodingException::forModel($this, $e->getMessage());
        }
    }

    /**
     * Serialize for JSON.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Escape when casting to string.
     */
    public function escapeWhenCastingToString(bool $escape = true): static
    {
        $this->escapeWhenCastingToString = $escape;

        return $this;
    }

    /**
     * Check if offset exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Get offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    /**
     * Set offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Get the client connection.
     */
    public function getConnection(): ClientHandler
    {
        return $this->connection;
    }

    /**
     * String representation.
     */
    public function __toString(): string
    {
        return $this->escapeWhenCastingToString ? e($this->toJson()) : $this->toJson();
    }

    /**
     * Get attribute dynamically.
     */
    public function __get($key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Set attribute dynamically.
     */
    public function __set($key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Check if attribute is set.
     */
    public function __isset($key): bool
    {
        return isset($this->attributes[$key]);
    }
}
