<?php

namespace Cerberus\Support;

use Illuminate\Contracts\Support\Arrayable;

class PaginatedCollection implements Arrayable
{
    /**
     * Create a new PaginatedCollection instance.
     *
     * @param  array<int, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public function __construct(public array $data, public array $meta = []) {}

    /**
     * Get the current page number.
     */
    public function currentPage(): ?int
    {
        return $this->meta['current_page'] ?? null;
    }

    /**
     * Get the number of items per page.
     */
    public function perPage(): ?int
    {
        return $this->meta['per_page'] ?? null;
    }

    /**
     * Get the total number of records (if available).
     */
    public function total(): ?int
    {
        return $this->meta['total'] ?? null;
    }

    /**
     * Get the last page number (if available).
     */
    public function lastPage(): ?int
    {
        return $this->meta['last_page'] ?? null;
    }

    /**
     * Determine if more pages exist after the current page.
     */
    public function hasMore(): bool
    {
        $current = $this->currentPage() ?? 1;
        $last = $this->lastPage() ?? $current;

        return $current < $last;
    }

    /**
     * Convert the paginated collection to a structured array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'meta' => $this->meta,
        ];
    }
}
