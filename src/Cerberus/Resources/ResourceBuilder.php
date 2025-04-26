<?php

namespace Cerberus\Resources;

use BadMethodCallException;
use Cerberus\Exceptions\ResourceException;
use Cerberus\Exceptions\ResourceNotFoundException;
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
     * @throws ResourceNotFoundException If API request fails
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

                throw new ResourceException('API returned status code: '.$response->getStatusCode());
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
     * @return array<int, resource>
     */
    public function findMany(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        // Some APIs support fetching multiple resources by ID in one request
        // If your API supports this, implement it here

        // Fallback to individual requests
        $results = [];
        foreach ($ids as $id) {
            try {
                if ($model = $this->find($id)) {
                    $results[] = $model;
                }
            } catch (Throwable $e) {
                // Continue processing other IDs even if one fails
                continue;
            }
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
                throw new ResourceException('API returned status code: '.$response->getStatusCode());
            }

            $responseData = $response->json();

            // Handle pagination wrapper if present
            if (isset($responseData['data']) && is_array($responseData['data'])) {
                $data = $responseData['data'];
            } elseif (is_array($responseData)) {
                $data = $responseData;
            } else {
                throw new ResourceException('Invalid response format from API');
            }

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
     * Get a Laravel-style paginator for the records.
     *
     * @param  int  $perPage  Number of items per page
     * @param  int  $page  Current page number
     * @param  string  $pageName  The query string variable used to store the page
     * @return array<string, mixed> Array with pagination metadata in Laravel format
     *
     * @throws ResourceException If the API request fails
     */
    public function paginate(int $perPage = 15, int $page = 1, string $pageName = 'page'): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        // Calculate offset
        $offset = ($page - 1) * $perPage;

        // Set limit and offset
        $this->limit($perPage);
        $this->offset($offset);

        try {
            $connection = $this->model->getConnection();

            // Build query parameters from constraints
            $queryParams = $this->buildQueryParameters();

            // Many APIs use page and per_page for pagination
            $queryParams['page'] = $page;
            $queryParams['per_page'] = $perPage;

            // Apply query parameters
            if (! empty($queryParams)) {
                $connection = $connection->withQueryParameters($queryParams);
            }

            $response = $connection->get("/{$this->model->getResourceName()}");

            if (! $response->ok()) {
                throw new ResourceException('API returned status code: '.$response->getStatusCode());
            }

            $responseData = $response->json();

            // Extract pagination metadata and data based on common API response patterns
            $data = [];
            $total = null;
            $lastPage = null;
            $from = null;
            $to = null;

            // Handle standard Laravel-style pagination responses
            if (isset($responseData['data']) && is_array($responseData['data'])) {
                $data = $responseData['data'];

                // Extract pagination metadata based on Laravel's pattern
                if (isset($responseData['meta'])) {
                    $meta = $responseData['meta'];
                    $total = $meta['total'] ?? null;
                    $lastPage = $meta['last_page'] ?? null;
                    $from = $meta['from'] ?? null;
                    $to = $meta['to'] ?? null;
                }
                // Some APIs put pagination info at the top level
                else {
                    $total = $responseData['total'] ?? null;
                    $lastPage = $responseData['last_page'] ?? null;
                    $from = $responseData['from'] ?? null;
                    $to = $responseData['to'] ?? null;
                }
            } elseif (is_array($responseData)) {
                // Non-standard response - just use the data directly
                $data = $responseData;
            } else {
                throw new ResourceException('Invalid response format from API');
            }

            // Calculate pagination metadata if not provided by the API
            if ($total === null) {
                // If we have a count endpoint, we could use it here
                // For now, we'll just use the count of returned items
                $total = count($data);
            }

            if ($lastPage === null && $total !== null && $perPage > 0) {
                $lastPage = max((int) ceil($total / $perPage), 1);
            }

            if ($from === null) {
                $from = $offset + 1;
            }

            if ($to === null) {
                $to = $offset + count($data);
            }

            // Create model instances from the response data
            $models = [];
            foreach ($data as $item) {
                $models[] = $this->model->newFromBuilder($item, $connection);
            }

            // Return in Laravel's LengthAwarePaginator format
            return [
                'data' => $models,
                'links' => [
                    'first' => $page > 1 ? $this->getPageUrl(1, $perPage, $pageName) : null,
                    'last' => $lastPage ? $this->getPageUrl($lastPage, $perPage, $pageName) : null,
                    'prev' => $page > 1 ? $this->getPageUrl($page - 1, $perPage, $pageName) : null,
                    'next' => $lastPage && $page < $lastPage ? $this->getPageUrl($page + 1, $perPage, $pageName) : null,
                ],
                'meta' => [
                    'current_page' => $page,
                    'from' => $from,
                    'last_page' => $lastPage,
                    'path' => $this->getCurrentUrl(),
                    'per_page' => $perPage,
                    'to' => $to,
                    'total' => $total,
                ],
            ];
        } catch (ResourceException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ResourceException('Error executing paginated query: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Get a simplified paginator that does not count the total number of results.
     *
     * @param  int  $perPage  Number of items per page
     * @param  int  $page  Current page number
     * @param  string  $pageName  The query string variable used to store the page
     * @return array<string, mixed> Array with pagination metadata in Laravel format
     */
    public function simplePaginate(int $perPage = 15, int $page = 1, string $pageName = 'page'): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        // Set limit and offset
        $this->limit($perPage + 1); // Get one extra item to determine if there's a next page
        $this->offset(($page - 1) * $perPage);

        // Get results (one more than needed to check if there's a next page)
        $results = $this->get();

        // Determine if there's a next page
        $hasMorePages = count($results) > $perPage;

        // Remove the extra item if it exists
        if ($hasMorePages) {
            array_pop($results);
        }

        // Return in Laravel's SimplePaginator format
        return [
            'data' => $results,
            'links' => [
                'prev' => $page > 1 ? $this->getPageUrl($page - 1, $perPage, $pageName) : null,
                'next' => $hasMorePages ? $this->getPageUrl($page + 1, $perPage, $pageName) : null,
            ],
            'meta' => [
                'current_page' => $page,
                'from' => count($results) > 0 ? (($page - 1) * $perPage) + 1 : null,
                'path' => $this->getCurrentUrl(),
                'per_page' => $perPage,
                'to' => count($results) > 0 ? (($page - 1) * $perPage) + count($results) : null,
            ],
        ];
    }

    /**
     * Execute the query and get the count of matching records.
     */
    public function count(): int
    {
        // Some APIs provide a count endpoint or parameter
        // This is a simplified implementation

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
                throw new ResourceException('API returned status code: '.$response->getStatusCode());
            }

            $responseData = $response->json();

            // Try to extract count from response
            if (isset($responseData['total'])) {
                return (int) $responseData['total'];
            } elseif (isset($responseData['meta']['total'])) {
                return (int) $responseData['meta']['total'];
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
     */
    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    /**
     * Chunk the results of the query.
     */
    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            // Clone the query to avoid modifying the original
            $clone = clone $this;
            $results = $clone->limit($count)->offset(($page - 1) * $count)->get();

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

                if ($operator === 'in' && is_array($value)) {
                    // Format for "where in" clauses
                    $params[$column] = implode(',', $value);
                } elseif ($operator === '=') {
                    // Simple equality
                    $params[$column] = $value;
                } else {
                    // Other operators - format will depend on your API
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
     * Get the current URL (without query string) for pagination links.
     *
     * This is a placeholder method since this is not a Laravel application.
     * In a real implementation, you would integrate with Laravel's URL generation.
     */
    protected function getCurrentUrl(): string
    {
        $baseUri = $this->getConnection()->getFullUri();

        return "{$baseUri}/".$this->model->getResourceName();
    }

    /**
     * Get a URL for a specific page.
     *
     * This is a placeholder method since this is not a Laravel application.
     * In a real implementation, you would integrate with Laravel's URL generation.
     */
    protected function getPageUrl(int $page, int $perPage, string $pageName): string
    {
        $baseUrl = $this->getCurrentUrl();

        return "{$baseUrl}?{$pageName}={$page}&per_page={$perPage}";
    }

    /**
     * Forward calls to the model.
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
        // Create deep copies of arrays to avoid modifying the original
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
