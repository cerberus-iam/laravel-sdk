<?php

namespace Cerberus\Resources;

use BadMethodCallException;
use Cerberus\Exceptions\ResourceException;
use Cerberus\Exceptions\ResourceNotFoundException;
use Cerberus\Support\PaginatedCollection;
use Throwable;

class ResourceBuilder
{
    /**
     * The model being queried.
     */
    protected Resource $model;

    /**
     * The columns that should be returned.
     *
     * @var array<int, string>|null
     */
    protected ?array $columns = null;

    /**
     * The where constraints for the query.
     *
     * @var array<string, mixed>
     */
    protected array $wheres = [];

    /**
     * The "order by" constraints for the query.
     *
     * @var array<string, string>
     */
    protected array $orders = [];

    /**
     * The maximum number of records to return.
     */
    protected ?int $limit = null;

    /**
     * The number of records to skip.
     */
    protected ?int $offset = null;

    /**
     * Create a new query builder instance.
     */
    public function __construct(Resource $model)
    {
        $this->model = $model;
    }

    /**
     * Set the columns to be selected.
     *
     * @param  array<int, string>|string  $columns
     * @return $this
     */
    public function select(array|string $columns): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @return $this
     */
    public function where(string|array $column, mixed $operator = null, mixed $value = null): self
    {
        // Handle array of where clauses
        if (is_array($column)) {
            foreach ($column as $key => $value) {
                $this->where($key, '=', $value);
            }

            return $this;
        }

        // If only two parameters are passed, assume operator is '='
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        // Store the operator along with the value for more complex filtering
        $this->wheres[$column] = [
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param  array<int, mixed>  $values
     * @return $this
     */
    public function whereIn(string $column, array $values): self
    {
        $this->wheres[$column] = [
            'operator' => 'in',
            'value' => $values,
        ];

        return $this;
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param  array<int, mixed>  $values
     * @return $this
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->wheres[$column] = [
            'operator' => 'not_in',
            'value' => $values,
        ];

        return $this;
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @return $this
     */
    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction);

        if (! in_array($direction, ['asc', 'desc'])) {
            $direction = 'asc';
        }

        $this->orders[$column] = $direction;

        return $this;
    }

    /**
     * Add a descending "order by" clause to the query.
     *
     * @return $this
     */
    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Set the "limit" value of the query.
     *
     * @return $this
     */
    public function limit(int $limit): self
    {
        $this->limit = max(1, $limit);

        return $this;
    }

    /**
     * Set the "take" value of the query.
     *
     * @return $this
     */
    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    /**
     * Set the "offset" value of the query.
     *
     * @return $this
     */
    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    /**
     * Set the "skip" value of the query.
     *
     * @return $this
     */
    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    /**
     * Execute the query and get the first result.
     */
    public function first(): ?Resource
    {
        // Limit to 1 record for efficiency
        $originalLimit = $this->limit;
        $this->limit = 1;

        $results = $this->get();

        // Restore original limit
        $this->limit = $originalLimit;

        return ! empty($results) ? $results[0] : null;
    }

    /**
     * Execute the query and get the first result or throw an exception.
     *
     * @throws ResourceNotFoundException If no resource is found.
     */
    public function firstOrFail(): Resource
    {
        $result = $this->first();

        if ($result === null) {
            throw new ResourceNotFoundException('No resource found matching the criteria');
        }

        return $result;
    }

    /**
     * Find a model by its primary key.
     *
     * @throws ResourceException If API request fails
     */
    public function find(mixed $id): ?Resource
    {
        try {
            $connection = $this->model->getConnection();
            $response = $connection->get("/{$this->model->getResourceName()}/{$id}");

            if (! $response->ok()) {
                if ($response->getStatusCode() === 404) {
                    return null;
                }

                throw new ResourceException(
                    "API returned error status code: {$response->getStatusCode()}",
                    $response->getStatusCode()
                );
            }

            $data = $this->parseResponseData($response);

            return empty($data) ? null : $this->model->newFromBuilder($data, $connection);
        } catch (ResourceNotFoundException $e) {
            return null;
        } catch (Throwable $e) {
            throw new ResourceException('Failed to find resource: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Find a model by its primary key or throw an exception.
     *
     * @throws ResourceNotFoundException If the resource cannot be found.
     */
    public function findOrFail(mixed $id): Resource
    {
        $result = $this->find($id);

        if ($result === null) {
            throw new ResourceNotFoundException("Resource with ID {$id} not found");
        }

        return $result;
    }

    /**
     * Find multiple models by their primary keys.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, resource>
     *
     * @throws ResourceException If a critical API error occurs
     */
    public function findMany(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // Try to use the whereIn method if available for a more efficient query
        if (count($ids) > 5) {
            try {
                return $this->whereIn('id', $ids)->get();
            } catch (Throwable $e) {
                // Fall back to individual requests if bulk request fails
            }
        }

        // Fallback to individual requests
        $results = [];
        $errors = [];

        foreach ($ids as $id) {
            try {
                if ($model = $this->find($id)) {
                    $results[] = $model;
                }
            } catch (ResourceNotFoundException $e) {
                // Skip not found resources
                continue;
            } catch (Throwable $e) {
                // Collect errors for later reporting
                $errors[] = "Failed to fetch ID {$id}: {$e->getMessage()}";
            }
        }

        // If we encountered errors, but still got some results, log the errors
        if (! empty($errors) && ! empty($results)) {
            // Log errors but don't throw
            // This could use a proper logger in a real implementation
            error_log(implode("\n", $errors));
        } elseif (! empty($errors)) {
            // If we got no results and had errors, throw an exception
            throw new ResourceException('Failed to fetch resources: '.implode('; ', $errors));
        }

        return $results;
    }

    /**
     * Execute the query and get all results.
     *
     * @return array<int, resource>
     *
     * @throws ResourceException If the API request fails
     */
    public function get(): array
    {
        try {
            $connection = $this->model->getConnection();

            // Build query parameters from constraints
            $queryParams = $this->buildQueryParameters();

            // Apply query parameters
            if (! empty($queryParams)) {
                $connection = $connection->withQueryParameters($queryParams);
            }

            $response = $connection->get("/{$this->model->getResourceName()}");

            if (! $response->ok()) {
                $statusCode = $response->getStatusCode();
                $errorMessage = $this->getErrorMessageFromResponse($response);

                throw new ResourceException(
                    "API returned error status code: {$statusCode} - {$errorMessage}",
                    $statusCode
                );
            }

            $data = $this->extractDataFromResponse($response);

            // Create model instances from the response data
            $models = [];
            foreach ($data as $item) {
                $models[] = $this->model->newFromBuilder($item, $connection);
            }

            return $models;
        } catch (ResourceException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ResourceException('Error executing query: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Get a paginated collection of records using the API response format.
     *
     * @throws ResourceException
     */
    public function paginate(int $perPage = 15, int $page = 1): PaginatedCollection
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        try {
            $connection = $this->model->getConnection();

            $queryParams = $this->buildQueryParameters();
            // Use API-specific pagination parameters
            $queryParams['page'] = $page;
            $queryParams['per_page'] = $perPage;
            // Clear limit and offset to avoid conflicts with page/per_page
            unset($queryParams['limit'], $queryParams['offset']);

            $connection = $connection->withQueryParameters($queryParams);
            $response = $connection->get("/{$this->model->getResourceName()}");

            if (! $response->ok()) {
                $statusCode = $response->getStatusCode();
                $errorMessage = $this->getErrorMessageFromResponse($response);

                throw new ResourceException(
                    "API returned error status code: {$statusCode} - {$errorMessage}",
                    $statusCode
                );
            }

            $responseData = $response->json();
            $rawData = $responseData['data'] ?? [];
            $meta = $responseData['meta'] ?? [];

            // Standardize pagination metadata
            $standardizedMeta = $this->standardizePaginationMeta($meta, $page, $perPage, count($rawData));

            $models = [];
            foreach ($rawData as $item) {
                $models[] = $this->model->newFromBuilder($item, $connection);
            }

            return new PaginatedCollection($models, $standardizedMeta);
        } catch (Throwable $e) {
            throw new ResourceException('Error executing paginated query: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Get a simplified paginated collection that does not rely on total counts.
     *
     * @throws ResourceException
     */
    public function simplePaginate(int $perPage = 15, int $page = 1): PaginatedCollection
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        try {
            $connection = $this->model->getConnection();

            $queryParams = $this->buildQueryParameters();
            // Request one more item than needed to determine if there are more pages
            $queryParams['limit'] = $perPage + 1;
            $queryParams['offset'] = ($page - 1) * $perPage;
            // Do not use page/per_page parameters for simplePaginate

            $connection = $connection->withQueryParameters($queryParams);
            $response = $connection->get("/{$this->model->getResourceName()}");

            if (! $response->ok()) {
                $statusCode = $response->getStatusCode();
                $errorMessage = $this->getErrorMessageFromResponse($response);

                throw new ResourceException(
                    "API returned error status code: {$statusCode} - {$errorMessage}",
                    $statusCode
                );
            }

            $data = $this->extractDataFromResponse($response);

            $hasMore = count($data) > $perPage;

            if ($hasMore) {
                array_pop($data);
            }

            $models = [];
            foreach ($data as $item) {
                $models[] = $this->model->newFromBuilder($item, $connection);
            }

            return new PaginatedCollection($models, [
                'current_page' => $page,
                'per_page' => $perPage,
                'has_more_pages' => $hasMore,
            ]);
        } catch (Throwable $e) {
            throw new ResourceException('Error executing simple paginated query: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Execute the query and get the count of matching records.
     *
     * @throws ResourceException
     */
    public function count(): int
    {
        try {
            $connection = $this->model->getConnection();

            // Build query parameters from constraints
            $queryParams = $this->buildQueryParameters();

            // Add count parameter if your API supports it
            $queryParams['count'] = true;

            // Apply query parameters
            if (! empty($queryParams)) {
                $connection = $connection->withQueryParameters($queryParams);
            }

            $response = $connection->get("/{$this->model->getResourceName()}");

            if (! $response->ok()) {
                $statusCode = $response->getStatusCode();
                $errorMessage = $this->getErrorMessageFromResponse($response);

                throw new ResourceException(
                    "API returned error status code: {$statusCode} - {$errorMessage}",
                    $statusCode
                );
            }

            $responseData = $response->json();

            // Try to extract count from response
            if (isset($responseData['total'])) {
                return (int) $responseData['total'];
            } elseif (isset($responseData['meta']['total'])) {
                return (int) $responseData['meta']['total'];
            } elseif (isset($responseData['count'])) {
                return (int) $responseData['count'];
            } elseif (isset($responseData['data']) && is_array($responseData['data'])) {
                // Fallback to counting the returned data
                return count($responseData['data']);
            } else {
                return count($responseData);
            }
        } catch (Throwable $e) {
            throw new ResourceException('Error getting count: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @throws ResourceException
     */
    public function exists(): bool
    {
        // Limit to 1 for efficiency
        $originalLimit = $this->limit;
        $this->limit = 1;

        $result = $this->get();

        // Restore original limit
        $this->limit = $originalLimit;

        return ! empty($result);
    }

    /**
     * Determine if no rows exist for the current query.
     *
     * @throws ResourceException
     */
    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    /**
     * Chunk the results of the query.
     *
     * @param  int  $count  Number of records per chunk
     * @param  callable  $callback  Function to process each chunk
     *
     * @throws ResourceException
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;
        $originalLimit = $this->limit;
        $originalOffset = $this->offset;

        try {
            do {
                // Instead of cloning the entire query, just update the pagination params
                $this->limit($count);
                $this->offset(($page - 1) * $count);

                $results = $this->get();
                $countResults = count($results);

                if ($countResults == 0) {
                    break;
                }

                // If the callback returns false, we stop processing
                if ($callback($results, $page) === false) {
                    return false;
                }

                unset($results);

                $page++;
            } while ($countResults == $count);

            return true;
        } finally {
            // Restore original pagination settings
            $this->limit = $originalLimit;
            $this->offset = $originalOffset;
        }
    }

    /**
     * Extract structured data from the API response.
     *
     * @param  mixed  $response  API response object
     * @return array<int, array<string, mixed>>
     *
     * @throws ResourceException If response format is invalid
     */
    protected function extractDataFromResponse($response): array
    {
        $responseData = $response->json();

        if (! is_array($responseData)) {
            throw new ResourceException('API response is not valid JSON');
        }

        // Handle different response formats
        if (isset($responseData['data']) && is_array($responseData['data'])) {
            return $responseData['data'];
        } elseif (is_array($responseData) && $this->isSequentialArray($responseData)) {
            return $responseData;
        } elseif (is_array($responseData) && ! empty($responseData)) {
            // Single item response
            return [$responseData];
        } else {
            throw new ResourceException('Invalid or empty response format from API');
        }
    }

    /**
     * Parse response data for single item requests.
     *
     * @param  mixed  $response  API response object
     * @return array<string, mixed>
     *
     * @throws ResourceException If response format is invalid
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
     * Extract error message from API response.
     *
     * @param  mixed  $response  API response object
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
     * Standardize pagination metadata from different API formats.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    protected function standardizePaginationMeta(array $meta, int $page, int $perPage, int $itemCount): array
    {
        $standardMeta = [
            'current_page' => $page,
            'per_page' => $perPage,
            'from' => (($page - 1) * $perPage) + 1,
            'to' => (($page - 1) * $perPage) + $itemCount,
        ];

        // Extract additional metadata if available
        if (isset($meta['total'])) {
            $standardMeta['total'] = (int) $meta['total'];
            $standardMeta['last_page'] = ceil($meta['total'] / $perPage);
        }

        // Handle other common API metadata formats
        if (isset($meta['page'])) {
            $standardMeta['current_page'] = (int) $meta['page'];
        }

        if (isset($meta['pages'])) {
            $standardMeta['last_page'] = (int) $meta['pages'];
        }

        if (isset($meta['count'])) {
            $standardMeta['total'] = (int) $meta['count'];
            if (! isset($standardMeta['last_page']) && $perPage > 0) {
                $standardMeta['last_page'] = ceil($meta['count'] / $perPage);
            }
        }

        return $standardMeta;
    }

    /**
     * Build query parameters from the current query constraints.
     *
     * @return array<string, mixed>
     */
    protected function buildQueryParameters(): array
    {
        $params = [];

        // Process where clauses
        foreach ($this->wheres as $column => $condition) {
            if (is_array($condition)) {
                $operator = $condition['operator'];
                $value = $condition['value'];

                switch ($operator) {
                    case 'in':
                        if (is_array($value)) {
                            $params[$column] = implode(',', $value);
                        }
                        break;
                    case 'not_in':
                        if (is_array($value)) {
                            $params["{$column}_not"] = implode(',', $value);
                        }
                        break;
                    case '=':
                        $params[$column] = $value;
                        break;
                    case '!=':
                    case '<>':
                        $params["{$column}_not"] = $value;
                        break;
                    case '>':
                        $params["{$column}_gt"] = $value;
                        break;
                    case '>=':
                        $params["{$column}_gte"] = $value;
                        break;
                    case '<':
                        $params["{$column}_lt"] = $value;
                        break;
                    case '<=':
                        $params["{$column}_lte"] = $value;
                        break;
                    case 'like':
                        $params["{$column}_like"] = $value;
                        break;
                    default:
                        // Default format for other operators
                        $params["{$column}_{$operator}"] = $value;
                }
            } else {
                // Simple value (backwards compatibility)
                $params[$column] = $condition;
            }
        }

        // Add sorting parameters
        if (! empty($this->orders)) {
            $orderParts = [];
            foreach ($this->orders as $column => $direction) {
                $orderParts[] = $direction === 'desc' ? "-{$column}" : $column;
            }
            $params['sort'] = implode(',', $orderParts);
        }

        // Add pagination parameters
        if ($this->limit !== null) {
            $params['limit'] = $this->limit;
        }

        if ($this->offset !== null) {
            $params['offset'] = $this->offset;
        }

        // Add fields selection if specified
        if ($this->columns !== null) {
            $params['fields'] = implode(',', $this->columns);
        }

        return $params;
    }

    /**
     * Check if an array is a sequential, numeric-indexed array.
     *
     * @param  array<mixed>  $array
     */
    protected function isSequentialArray(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Forward calls to the model.
     *
     * @param  array<mixed>  $parameters
     *
     * @throws \BadMethodCallException When the method does not exist
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Forward to the model if possible
        if (method_exists($this->model, $method)) {
            return $this->model->{$method}(...$parameters);
        }

        throw new BadMethodCallException("Method {$method} does not exist on ".get_class($this));
    }

    /**
     * Clone the query.
     */
    public function __clone()
    {
        // Create deep copies of arrays
        $this->wheres = array_map(function ($item) {
            return is_array($item)
                ? array_map(fn ($value) => is_object($value) ? clone $value : $value, $item)
                : (is_object($item) ? clone $item : $item);
        }, $this->wheres);

        // Deep copy columns if not null
        if ($this->columns !== null) {
            $this->columns = array_map(
                fn ($column) => is_object($column) ? clone $column : $column,
                $this->columns
            );
        }

        // Deep copy orders
        $this->orders = array_map(
            fn ($order) => is_object($order) ? clone $order : $order,
            $this->orders
        );
    }
}
