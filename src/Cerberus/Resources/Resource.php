<?php

namespace Cerberus\Resources;

use ArrayAccess;
use Cerberus\Exceptions\MassAssignmentException;
use Cerberus\Exceptions\ResourceCreationException;
use Cerberus\Exceptions\ResourceDeleteException;
use Cerberus\Exceptions\ResourceException;
use Cerberus\Exceptions\ResourceNotFoundException;
use Cerberus\Exceptions\ResourceUpdateException;
use Fetch\Interfaces\ClientHandler;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use JsonSerializable;
use Stringable;
use Symfony\Component\HttpFoundation\Exception\JsonException;

/**
 * @mixin \Illuminate\Support\Traits\ForwardsCalls
 */
abstract class Resource implements Arrayable, ArrayAccess, Jsonable, JsonSerializable, Stringable
{
    use ForwardsCalls;

    /**
     * Indicates if the resource exists.
     */
    public bool $exists = false;

    /**
     * The resource endpoint name.
     */
    public string $resource;

    /**
     * The filters applied to the resource.
     */
    protected array $filters = [];

    /**
     * The attributes of the resource.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * The original attributes of the resource.
     *
     * @var array<string, mixed>
     */
    protected array $original = [];

    /**
     * The primary key of the resource.
     */
    protected string $primaryKey = 'uid';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected array $fillable = [];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected array $guarded = ['*'];

    /**
     * Indicates which attributes should be hidden when serializing to array/JSON.
     *
     * @var array<int, string>
     */
    protected array $hidden = [];

    /**
     * The client connection instance.
     */
    protected ?ClientHandler $connection = null;

    /**
     * Indicates if string output should be escaped.
     */
    protected bool $escapeWhenCastingToString = false;

    /**
     * Create a new resource instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->resource = $this->resource ?? Str::plural(Str::snake(class_basename($this)));
        $this->fill($attributes);
        $this->syncOriginal();
    }

    /**
     * Create a new instance.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function make(array $attributes = []): static
    {
        return new static($attributes);
    }

    /**
     * Get a new query builder for the model's table.
     */
    public static function query(): ResourceBuilder
    {
        return (new static)->newQuery();
    }

    /**
     * Begin querying the model.
     */
    public static function where($column, $operator = null, $value = null): ResourceBuilder
    {
        return static::query()->where($column, $operator, $value);
    }

    /**
     * Add a where-in condition to the query.
     */
    public static function whereIn(string $column, array $values): ResourceBuilder
    {
        return static::query()->whereIn($column, $values);
    }

    /**
     * Find a model by its primary key.
     *
     * @throws ResourceNotFoundException If the resource cannot be found.
     */
    public static function find(mixed $id): mixed
    {
        if ($id === null) {
            return null;
        }

        // Check if the ID is an array and handle accordingly
        if (is_array($id)) {
            return static::findMany($id);
        }

        // Attempt to find the model by its primary key
        return static::query()->find($id);
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @throws ResourceNotFoundException If the resource cannot be found.
     */
    public static function findOrFail(mixed $id): static
    {
        $result = static::find($id);

        if ($result === null) {
            throw new ResourceNotFoundException("Resource with ID {$id} not found.");
        }

        return $result;
    }

    /**
     * Find multiple models by their primary keys.
     *
     * @return array<int, static>
     */
    public static function findMany(array $ids): array
    {
        return static::query()->findMany($ids);
    }

    /**
     * Execute the query and get the first result.
     */
    public static function first(): ?static
    {
        return static::query()->first();
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @throws ResourceNotFoundException If no resource is found.
     */
    public static function firstOrFail(): static
    {
        $result = static::first();

        if ($result === null) {
            throw new ResourceNotFoundException('No resource found matching the criteria.');
        }

        return $result;
    }

    /**
     * Get all of the models from the database.
     *
     * @return array<int, static>
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * Create a new model and persist it to the database.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ResourceCreationException If the resource cannot be created.
     */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);

        if (! $model->save()) {
            throw new ResourceCreationException('Failed to create resource.');
        }

        return $model;
    }

    /**
     * Destroy the models for the given IDs.
     *
     * @param  array|int|string  $ids
     * @return int Number of resources successfully deleted
     */
    public static function destroy($ids): int
    {
        $count = 0;

        if (! is_array($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as $id) {
            try {
                if ($model = static::find($id)) {
                    if ($model->delete()) {
                        $count++;
                    }
                }
            } catch (\Exception $e) {
                // Continue processing other IDs even if one fails
                continue;
            }
        }

        return $count;
    }

    /**
     * Create a new model instance that is existing.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newFromBuilder($attributes = [], ?ClientHandler $connection = null): static
    {
        $model = $this->newInstance([], true);

        $model->setRawAttributes((array) $attributes, true);

        if ($connection) {
            $model->setConnection($connection);
        }

        return $model;
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        $model = new static($attributes);

        $model->exists = $exists;

        // Transfer the connection if it exists
        if ($this->connection !== null) {
            $model->setConnection($this->connection);
        }

        return $model;
    }

    /**
     * Get an attribute from the model.
     */
    public function getAttribute(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Set a given attribute on the model.
     *
     * @return $this
     */
    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Set the array of model attributes. No checking is done.
     *
     * @param  array<string, mixed>  $attributes
     * @return $this
     */
    public function setRawAttributes(array $attributes, bool $sync = false): static
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array<string, mixed>  $attributes
     * @return $this
     *
     * @throws MassAssignmentException
     */
    public function fill(array $attributes): static
    {
        $fillable = $this->fillableFromArray($attributes);

        if ($this->totallyGuarded() && ! empty($fillable)) {
            throw new MassAssignmentException(
                sprintf(
                    'Add [%s] to fillable property to allow mass assignment on [%s].',
                    implode(', ', array_keys($fillable)),
                    static::class
                )
            );
        }

        foreach ($fillable as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes. Force mass assignment.
     *
     * @param  array<string, mixed>  $attributes
     * @return $this
     */
    public function forceFill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Determine if the model is totally guarded.
     */
    public function totallyGuarded(): bool
    {
        return count($this->fillable) === 0 && $this->guarded === ['*'];
    }

    /**
     * Get the primary key for the model.
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Set the primary key for the model.
     *
     * @return $this
     */
    public function setKeyName(string $key): static
    {
        $this->primaryKey = $key;

        return $this;
    }

    /**
     * Get the value of the model's primary key.
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Get the resource endpoint name.
     */
    public function getResourceName(): string
    {
        return $this->resource;
    }

    /**
     * Create a new Eloquent query builder for the model.
     */
    public function newQuery(): ResourceBuilder
    {
        return new ResourceBuilder($this);
    }

    /**
     * Save the model to the database.
     *
     * @throws ResourceUpdateException|ResourceCreationException When the save operation fails.
     */
    public function save(): bool
    {
        try {
            if ($this->exists) {
                if (! $this->isDirty()) {
                    return true;
                }

                $dirty = $this->getDirty();
                $this->performUpdate($dirty);
            } else {
                $this->performInsert();
            }

            return true;
        } catch (ResourceException $e) {
            // Re-throw our custom exceptions
            throw $e;
        } catch (\Exception $e) {
            // Convert general exceptions to our custom exceptions
            if ($this->exists) {
                throw new ResourceUpdateException('Failed to update resource: '.$e->getMessage(), 0, $e);
            } else {
                throw new ResourceCreationException('Failed to create resource: '.$e->getMessage(), 0, $e);
            }
        }
    }

    /**
     * Update the model in the database.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ResourceUpdateException If the update fails.
     */
    public function update(array $attributes = []): bool
    {
        if (! $this->exists) {
            throw new ResourceUpdateException("Cannot update a resource that doesn't exist");
        }

        $this->fill($attributes);

        return $this->save();
    }

    /**
     * Delete the model from the database.
     *
     * @throws ResourceDeleteException If the deletion fails.
     */
    public function delete(): bool
    {
        if (! $this->exists) {
            return true; // Already doesn't exist, so technically successful
        }

        $key = $this->getKey();

        if ($key === null) {
            throw new ResourceDeleteException('Cannot delete a resource without a primary key');
        }

        try {
            $response = $this->getConnection()->delete("/{$this->resource}/{$key}");

            if (! $response->successful()) {
                throw new ResourceDeleteException('API returned status code: '.$response->status());
            }

            $this->exists = false;

            return true;
        } catch (ResourceDeleteException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ResourceDeleteException('Failed to delete resource: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Force a hard delete on a soft deleted model.
     *
     * @throws ResourceDeleteException If the deletion fails.
     */
    public function forceDelete(): bool
    {
        return $this->delete();
    }

    /**
     * Determine if the model instance has been soft-deleted.
     */
    public function trashed(): bool
    {
        return false;
    }

    /**
     * Determine if two models have the same ID and belong to the same table.
     */
    public function is(?Resource $model): bool
    {
        return $model !== null &&
               $model->getKey() == $this->getKey() &&
               $model->getResourceName() === $this->getResourceName();
    }

    /**
     * Determine if two models are not the same.
     */
    public function isNot(?Resource $model): bool
    {
        return ! $this->is($model);
    }

    /**
     * Determine if the model has been modified since last sync.
     *
     * @param  array<string>|string|null  $attributes
     */
    public function isDirty(array|string|null $attributes = null): bool
    {
        return count($this->getDirty($attributes)) > 0;
    }

    /**
     * Get the attributes that have been changed since last sync.
     *
     * @param  array<string>|string|null  $attributes
     * @return array<string, mixed>
     */
    public function getDirty(array|string|null $attributes = null): array
    {
        $dirty = [];

        if (is_null($attributes)) {
            $attributes = array_keys($this->attributes);
        }

        $attributes = is_array($attributes) ? $attributes : [$attributes];

        foreach ($attributes as $key) {
            if (! array_key_exists($key, $this->original) ||
                $this->attributes[$key] !== $this->original[$key]) {
                $dirty[$key] = $this->attributes[$key];
            }

            if (! array_key_exists($key, $this->original)) {
                continue; // was never known, don't assume it's dirty
            }

            if ($this->attributes[$key] !== $this->original[$key]) {
                $dirty[$key] = $this->attributes[$key];
            }
        }

        return $dirty;
    }

    /**
     * Determine if the model has any dirty attributes.
     */
    public function wasChanged(): bool
    {
        return ! empty($this->getDirty());
    }

    /**
     * Sync the original attributes with the current.
     *
     * @return $this
     */
    public function syncOriginal(): static
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Convert the model instance to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $attributes = $this->attributes;

        // Remove hidden attributes
        if (! empty($this->hidden)) {
            $attributes = array_diff_key($attributes, array_flip($this->hidden));
        }

        return $attributes;
    }

    /**
     * Convert the model instance to JSON.
     *
     * @param  int  $options
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
     * Convert the object into something JSON serializable.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Determine if the given attribute exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Get the value for a given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value for a given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value for a given offset.
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
        if ($this->connection === null) {
            $this->connection = Container::getInstance()->make(ClientHandler::class);
        }

        return $this->connection;
    }

    /**
     * Set the client connection.
     *
     * @return $this
     */
    public function setConnection(ClientHandler $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Get the fillable attributes of a given array.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function fillableFromArray(array $attributes): array
    {
        if (count($this->fillable) > 0 && ! $this->totallyGuarded()) {
            return array_intersect_key($attributes, array_flip($this->fillable));
        }

        if (count($this->guarded) > 0 && $this->guarded[0] !== '*') {
            return array_diff_key($attributes, array_flip($this->guarded));
        }

        return $attributes;
    }

    /**
     * Create a new model instance using attributes from the API response.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function newModelFromBuilder(array $attributes): static
    {
        $model = $this->newInstance([], true);
        $model->setRawAttributes($attributes, true);

        return $model;
    }

    /**
     * Perform a model insert operation.
     *
     * @throws ResourceCreationException If the creation fails or returns invalid data.
     */
    protected function performInsert(): void
    {
        $response = $this->getConnection()->post("/{$this->resource}", $this->attributes);

        if (! $response->successful()) {
            throw new ResourceCreationException('API returned status code: '.$response->status());
        }

        $data = $this->parseResponseData($response);

        // Update the model with the returned data
        if (is_array($data)) {
            $this->fill($data);
        } else {
            throw new ResourceCreationException('API response did not contain valid resource data');
        }

        $this->exists = true;
        $this->syncOriginal();
    }

    /**
     * Perform a model update operation.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ResourceUpdateException If the update fails or returns invalid data.
     */
    protected function performUpdate(array $attributes): void
    {
        $key = $this->getKey();

        if ($key === null) {
            throw new ResourceUpdateException('Cannot update a resource without a primary key.');
        }

        $response = $this->getConnection()->put("/{$this->resource}/{$key}", $attributes);

        if (! $response->successful()) {
            throw new ResourceUpdateException('API returned status code: '.$response->status());
        }

        $data = $this->parseResponseData($response);

        // Update the model with the returned data
        if (is_array($data)) {
            $this->fill($data);
        }

        $this->syncOriginal();
    }

    /**
     * Parse response data from API responses.
     *
     * @return array<string, mixed>
     */
    protected function parseResponseData($response): array
    {
        $json = $response->json();

        if (! is_array($json)) {
            throw new ResourceException('API response is not valid JSON');
        }

        // Check if the response has a 'data' wrapper
        return $json['data'] ?? $json;
    }

    /**
     * Dynamically retrieve attributes on the model.
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if an attribute or relation exists on the model.
     */
    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Unset an attribute on the model.
     */
    public function __unset(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Convert the model to its string representation.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Handle dynamic static method calls into the model.
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Handle dynamic method calls into the model.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this, $method)) {
            return $this->{$method}(...$parameters);
        }

        return $this->newQuery()->$method(...$parameters);
    }
}
