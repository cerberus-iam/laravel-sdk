<?php

namespace Cerberus\Resources;

use ArrayAccess;
use Cerberus\Exceptions\MassAssignmentException;
use Cerberus\Exceptions\ResourceCreationException;
use Cerberus\Exceptions\ResourceDeleteException;
use Cerberus\Exceptions\ResourceException;
use Cerberus\Exceptions\ResourceNotFoundException;
use Cerberus\Exceptions\ResourceUpdateException;
use DateTime;
use Fetch\Interfaces\ClientHandler;
use Illuminate\Container\Container;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Support\Arr;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\ForwardsCalls;
use JsonSerializable;
use Stringable;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Throwable;

/**
 * @mixin \Illuminate\Support\Traits\ForwardsCalls
 */
abstract class Resource implements Arrayable, ArrayAccess, Jsonable, JsonSerializable, Stringable
{
    use ForwardsCalls;

    /**
     * Indicates whether the resource has been persisted to the API.
     */
    public bool $exists = false;

    /**
     * The name of the API resource endpoint (e.g., "users", "projects").
     */
    public string $resource;

    /**
     * Filters applied to the resource query.
     */
    protected array $filters = [];

    /**
     * Current attribute values for the resource.
     */
    protected array $attributes = [];

    /**
     * Original attribute values for tracking changes.
     */
    protected array $original = [];

    /**
     * The name of the primary key for the resource.
     */
    protected string $primaryKey = 'uid';

    /**
     * Attributes that are mass assignable.
     */
    protected array $fillable = [];

    /**
     * Attributes that are not mass assignable.
     */
    protected array $guarded = ['*'];

    /**
     * Attributes that should be hidden when converting to array or JSON.
     */
    protected array $hidden = [];

    /**
     * Attributes and their corresponding cast types.
     */
    protected array $casts = [];

    /**
     * The API client handler instance.
     */
    protected ?ClientHandler $connection = null;

    /**
     * Whether to escape JSON string output.
     */
    protected bool $escapeWhenCastingToString = false;

    /**
     * Indicates whether the model should track created_at and updated_at timestamps.
     */
    protected bool $timestamps = false;

    /**
     * The column name used for the "created at" timestamp.
     */
    protected string $createdAtColumn = 'created_at';

    /**
     * The column name used for the "updated at" timestamp.
     */
    protected string $updatedAtColumn = 'updated_at';

    /**
     * A fluent wrapper instance used for nested key access.
     */
    protected ?Fluent $fluent = null;

    /**
     * Create a new resource instance and fill with attributes.
     */
    public function __construct(array $attributes = [])
    {
        $this->resource = $this->resource ?? Str::plural(Str::snake(class_basename($this)));
        $this->fill($attributes);
        $this->syncOriginal();
    }

    /**
     * Create a new instance of the resource with the given attributes.
     */
    public static function make(array $attributes = []): static
    {
        return new static($attributes);
    }

    /**
     * Start a new query builder for the resource.
     */
    public static function query(): ResourceBuilder
    {
        return (new static)->newQuery();
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  mixed  $column
     * @param  mixed|null  $operator
     * @param  mixed|null  $value
     */
    public static function where($column, $operator = null, $value = null): ResourceBuilder
    {
        return static::query()->where($column, $operator, $value);
    }

    /**
     * Add a "where in" clause to the query.
     */
    public static function whereIn(string $column, array $values): ResourceBuilder
    {
        return static::query()->whereIn($column, $values);
    }

    /**
     * Add a "where not in" clause to the query.
     */
    public static function whereNotIn(string $column, array $values): ResourceBuilder
    {
        return static::query()->whereNotIn($column, $values);
    }

    /**
     * Find a resource by its primary key.
     */
    public static function find(mixed $id): mixed
    {
        if ($id === null) {
            return null;
        }

        if (is_array($id)) {
            return static::findMany($id);
        }

        return static::query()->find($id);
    }

    /**
     * Find a resource by its primary key or throw an exception.
     *
     * @throws ResourceNotFoundException
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
     * Find multiple resources by their primary keys.
     *
     * @return array<int, static>
     */
    public static function findMany(array $ids): array
    {
        return static::query()->findMany($ids);
    }

    /**
     * Get the first result of the query.
     */
    public static function first(): ?static
    {
        return static::query()->first();
    }

    /**
     * Get the first result or throw an exception.
     *
     *
     * @throws ResourceNotFoundException
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
     * Get all results for the resource.
     *
     * @return array<int, static>
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * Create a new resource via the API.
     *
     *
     * @throws ResourceCreationException
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
     * Update an existing resource or create a new one.
     */
    public static function updateOrCreate(array $attributes, array $values = []): static
    {
        $values = array_merge($attributes, $values);
        $primaryKey = (new static)->getKeyName();

        if (isset($attributes[$primaryKey])) {
            try {
                $model = static::findOrFail($attributes[$primaryKey]);
                $model->fill($values);
                $model->save();

                return $model;
            } catch (ResourceNotFoundException $e) {
                // Resource not found with primary key, proceed to create
            }
        } else {
            // Try to find by attributes
            $model = static::where($attributes)->first();

            if ($model !== null) {
                $model->fill($values);
                $model->save();

                return $model;
            }
        }

        return static::create($values);
    }

    /**
     * Delete one or many resources by ID.
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

        $errors = [];
        foreach ($ids as $id) {
            try {
                if ($model = static::find($id)) {
                    if ($model->delete()) {
                        $count++;
                    }
                }
            } catch (Throwable $e) {
                $errors[] = "Failed to delete ID {$id}: {$e->getMessage()}";
            }
        }

        if (! empty($errors)) {
            error_log(implode("\n", $errors));
        }

        return $count;
    }

    /**
     * Check if a resource exists by the given attributes.
     */
    public static function exists(array $attributes): bool
    {
        return static::where($attributes)->exists();
    }

    /**
     * Convert an array of raw data into resource instances.
     *
     * @return array<int, static>
     */
    public static function hydrate(array $items): array
    {
        $models = [];
        $instance = new static;

        foreach ($items as $item) {
            $models[] = $instance->newModelFromBuilder($item);
        }

        return $models;
    }

    /**
     * Create a new instance from raw builder attributes.
     *
     * @param  array  $attributes
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
     * Create a new instance of the current model.
     */
    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        $model = new static($attributes);
        $model->exists = $exists;

        if ($this->connection !== null) {
            $model->setConnection($this->connection);
        }

        return $model;
    }

    /**
     * Get the value of a given attribute with casting applied.
     *
     * @param  string  $key
     */
    public function getAttribute($key): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            $value = $this->attributes[$key];

            if (isset($this->casts[$key])) {
                return $this->castAttribute($key, $value);
            }

            return $value;
        }

        return null;
    }

    /**
     * Get an attribute's value using fluent syntax.
     *
     * @param  mixed|null  $default
     */
    public function get(string $key, $default = null): mixed
    {
        if ($this->fluent === null) {
            $this->fluent = new Fluent($this->attributes);
        } else {
            $this->fluent->fill($this->attributes);
        }

        return $this->fluent->get($key, $default);
    }

    /**
     * Set a given attribute using fluent syntax.
     *
     * @param  mixed  $value
     */
    public function set(string $key, $value): static
    {
        if ($this->fluent === null) {
            $this->fluent = new Fluent($this->attributes);
        }

        $this->fluent->set($key, $value);

        if (strpos($key, '.') === false) {
            $this->setAttribute($key, $value);
        } else {
            $rootKey = explode('.', $key)[0];

            if (isset($this->fluent->{$rootKey})) {
                $this->setAttribute($rootKey, $this->fluent->{$rootKey});
            }
        }

        return $this;
    }

    /**
     * Set the raw value of an attribute with optional casting.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    public function setAttribute($key, $value): static
    {
        if (isset($this->casts[$key])) {
            $value = $this->castValueForStorage($key, $value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Set raw attributes on the model.
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
     * Fill the model with an array of attributes while considering mass assignment rules.
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
     * Force fill the model with the given attributes without checking guard rules.
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
     * Get the name of the primary key for the resource.
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Set the name of the primary key for the resource.
     */
    public function setKeyName(string $key): static
    {
        $this->primaryKey = $key;

        return $this;
    }

    /**
     * Get the primary key value of the resource.
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Get the resource name associated with the model.
     */
    public function getResourceName(): string
    {
        return $this->resource;
    }

    /**
     * Get a new query builder for the model.
     */
    public function newQuery(): ResourceBuilder
    {
        return new ResourceBuilder($this);
    }

    /**
     * Save the model to the remote API.
     *
     *
     * @throws ResourceCreationException|ResourceUpdateException
     */
    public function save(): bool
    {
        try {
            if ($this->timestamps) {
                $this->updateTimestamps();
            }

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
            throw $e;
        } catch (Throwable $e) {
            if ($this->exists) {
                throw new ResourceUpdateException('Failed to update resource: '.$e->getMessage(), 0, $e);
            } else {
                throw new ResourceCreationException('Failed to create resource: '.$e->getMessage(), 0, $e);
            }
        }
    }

    /**
     * Update the resource with new attributes.
     *
     * @throws ResourceUpdateException
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
     * Delete the resource via the API.
     *
     * @throws ResourceDeleteException
     */
    public function delete(): bool
    {
        if (! $this->exists) {
            return true;
        }

        $key = $this->getKey();

        if ($key === null) {
            throw new ResourceDeleteException('Cannot delete a resource without a primary key');
        }

        try {
            $response = $this->getConnection()->delete("/{$this->resource}/{$key}");

            if (! $response->ok()) {
                $statusCode = $response->getStatusCode();
                $errorMessage = $this->getErrorMessageFromResponse($response);

                throw new ResourceDeleteException(
                    "API returned error status code: {$statusCode} - {$errorMessage}",
                    $statusCode
                );
            }

            $this->exists = false;

            return true;
        } catch (ResourceDeleteException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ResourceDeleteException('Failed to delete resource: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Force delete the resource (alias for delete).
     */
    public function forceDelete(): bool
    {
        return $this->delete();
    }

    /**
     * Determine if the resource has been soft deleted.
     */
    public function trashed(): bool
    {
        return false;
    }

    /**
     * Check if the given model instance is the same as the current one.
     */
    public function is(?Resource $model): bool
    {
        return $model !== null &&
               $model->getKey() == $this->getKey() &&
               $model->getResourceName() === $this->getResourceName();
    }

    /**
     * Check if the given model instance is different from the current one.
     */
    public function isNot(?Resource $model): bool
    {
        return ! $this->is($model);
    }

    /**
     * Determine if any attributes have been modified.
     */
    public function isDirty(array|string|null $attributes = null): bool
    {
        return count($this->getDirty($attributes)) > 0;
    }

    /**
     * Get the attributes that have been changed since last sync.
     *
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
            if (! array_key_exists($key, $this->attributes)) {
                continue;
            }

            if (! array_key_exists($key, $this->original) ||
                $this->attributes[$key] !== $this->original[$key]) {
                $dirty[$key] = $this->attributes[$key];
            }
        }

        return $dirty;
    }

    /**
     * Determine if any attributes were changed since the last sync.
     */
    public function wasChanged(): bool
    {
        return ! empty($this->getDirty());
    }

    /**
     * Sync the original attributes with the current state.
     */
    public function syncOriginal(): static
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Reload the current model instance from the API.
     *
     *
     * @throws ResourceNotFoundException
     */
    public function refresh(): static
    {
        if (! $this->exists) {
            throw new ResourceNotFoundException("Cannot refresh a resource that doesn't exist.");
        }

        $key = $this->getKey();

        if ($key === null) {
            throw new ResourceNotFoundException('Cannot refresh a resource without a primary key.');
        }

        $updated = static::findOrFail($key);

        $this->attributes = $updated->attributes;
        $this->syncOriginal();

        return $this;
    }

    /**
     * Determine if a specific attribute or any attribute has changed.
     */
    public function hasChanged(string|array|null $attribute = null): bool
    {
        if (is_null($attribute)) {
            return $this->wasChanged();
        }

        if (is_array($attribute)) {
            foreach ($attribute as $key) {
                if ($this->hasChanged($key)) {
                    return true;
                }
            }

            return false;
        }

        return array_key_exists($attribute, $this->attributes) &&
               array_key_exists($attribute, $this->original) &&
               $this->attributes[$attribute] !== $this->original[$attribute];
    }

    /**
     * Return only the specified attributes.
     *
     * @param  array|string|null  $keys
     */
    public function only($keys = null): array
    {
        if (is_null($keys)) {
            return $this->attributes;
        }

        $keys = is_array($keys) ? $keys : func_get_args();

        return Arr::only($this->attributes, $keys);
    }

    /**
     * Return the attributes except those specified.
     *
     * @param  array|string  $keys
     */
    public function except($keys): array
    {
        $keys = is_array($keys) ? $keys : func_get_args();

        return Arr::except($this->attributes, $keys);
    }

    /**
     * Convert the model instance to an array.
     */
    public function toArray(): array
    {
        $attributes = $this->attributes;

        foreach ($this->casts as $key => $type) {
            if (array_key_exists($key, $attributes)) {
                $attributes[$key] = $this->castAttribute($key, $attributes[$key]);
            }
        }

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
     * @throws JsonEncodingException
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
     * Prepare the model for JSON serialization.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Determine if the given offset exists.
     *
     * @param  mixed  $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Get the value at the given offset.
     *
     * @param  mixed  $offset
     */
    public function offsetGet($offset): mixed
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value at the given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value at the given offset.
     *
     * @param  mixed  $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Get the API connection instance.
     */
    public function getConnection(): ClientHandler
    {
        if ($this->connection === null) {
            $this->connection = Container::getInstance()->make(ClientHandler::class);
        }

        return $this->connection;
    }

    /**
     * Set the API connection instance.
     */
    public function setConnection(ClientHandler $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Cast an attribute's value when getting.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    protected function castAttribute($key, $value): mixed
    {
        $castType = $this->casts[$key];

        if ($value === null) {
            return null;
        }

        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'array':
                return is_array($value) ? $value : json_decode((string) $value, true);
            case 'json':
                return json_decode((string) $value, true);
            case 'datetime':
            case 'date':
                return new DateTime((string) $value);
            default:
                return $value;
        }
    }

    /**
     * Cast a value for storage before persisting.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    protected function castValueForStorage($key, $value): mixed
    {
        $castType = $this->casts[$key];

        if ($value === null) {
            return null;
        }

        switch ($castType) {
            case 'array':
            case 'json':
                return is_array($value) ? json_encode($value) : $value;
            case 'datetime':
            case 'date':
                if ($value instanceof DateTime) {
                    return $value->format('Y-m-d H:i:s');
                }

                return $value;
            default:
                return $value;
        }
    }

    /**
     * Update the timestamps on the model.
     */
    protected function updateTimestamps(): void
    {
        $time = date('Y-m-d H:i:s');

        if (! $this->exists && ! isset($this->attributes[$this->createdAtColumn])) {
            $this->attributes[$this->createdAtColumn] = $time;
        }

        if (! isset($this->attributes[$this->updatedAtColumn])) {
            $this->attributes[$this->updatedAtColumn] = $time;
        }
    }

    /**
     * Filter the provided attributes according to the fillable and guarded settings.
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
     * Create a new model instance from raw API attributes.
     */
    protected function newModelFromBuilder(array $attributes): static
    {
        $model = $this->newInstance([], true);
        $model->setRawAttributes($attributes, true);

        return $model;
    }

    /**
     * Perform an API request to insert a new resource.
     *
     * @throws ResourceCreationException
     */
    protected function performInsert(): void
    {
        $response = $this->getConnection()->post("/{$this->resource}", $this->attributes);

        if (! $response->ok()) {
            $statusCode = $response->getStatusCode();
            $errorMessage = $this->getErrorMessageFromResponse($response);

            throw new ResourceCreationException(
                "API returned error status code: {$statusCode} - {$errorMessage}",
                $statusCode
            );
        }

        $data = $this->parseResponseData($response);

        if (is_array($data)) {
            $this->fill($data);
        } else {
            throw new ResourceCreationException('API response did not contain valid resource data');
        }

        $this->exists = true;
        $this->syncOriginal();
    }

    /**
     * Perform an API request to update an existing resource.
     *
     * @throws ResourceUpdateException
     */
    protected function performUpdate(array $attributes): void
    {
        $key = $this->getKey();

        if ($key === null) {
            throw new ResourceUpdateException('Cannot update a resource without a primary key.');
        }

        $response = $this->getConnection()->put("/{$this->resource}/{$key}", $attributes);

        if (! $response->ok()) {
            $statusCode = $response->getStatusCode();
            $errorMessage = $this->getErrorMessageFromResponse($response);

            throw new ResourceUpdateException(
                "API returned error status code: {$statusCode} - {$errorMessage}",
                $statusCode
            );
        }

        $data = $this->parseResponseData($response);

        if (is_array($data)) {
            $this->fill($data);
        }

        $this->syncOriginal();
    }

    /**
     * Extract an error message from the API response.
     *
     * @param  mixed  $response
     */
    protected function getErrorMessageFromResponse($response): string
    {
        try {
            $data = $response->json();

            if (isset($data['error']['message'])) {
                return $data['error']['message'];
            } elseif (isset($data['error'])) {
                return is_string($data['error']) ? $data['error'] : 'Unknown error';
            } elseif (isset($data['message'])) {
                return $data['message'];
            }
        } catch (Throwable $e) {
            // Cannot parse response as JSON
        }

        return 'Unknown error';
    }

    /**
     * Parse the resource data from the API response.
     *
     * @param  mixed  $response
     *
     * @throws ResourceException
     */
    protected function parseResponseData($response): array
    {
        $json = $response->json();

        if (! is_array($json)) {
            throw new ResourceException('API response is not valid JSON');
        }

        return $json['data'] ?? $json;
    }

    /**
     * Dynamically retrieve attribute.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attribute.
     *
     * @param  mixed  $value
     * @return void
     */
    public function __set(string $key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Check if an attribute is set.
     *
     * @return bool
     */
    public function __isset(string $key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Unset an attribute.
     *
     * @return void
     */
    public function __unset(string $key)
    {
        unset($this->attributes[$key]);
    }

    /**
     * Convert the resource instance to a JSON string.
     */
    public function __toString(): string
    {
        try {
            return $this->toJson();
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * Handle dynamic static method calls.
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Handle dynamic method calls.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this, $method)) {
            return $this->{$method}(...$parameters);
        }

        return $this->newQuery()->$method(...$parameters);
    }
}
