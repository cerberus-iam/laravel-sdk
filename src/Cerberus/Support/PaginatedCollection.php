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
     * @param  array<string, mixed>  $links
     * @return void
     */
    public function __construct(
        public array $data,
        public array $meta = [],
        public array $links = []
    ) {}

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
     * Get the first item index on this page.
     */
    public function from(): ?int
    {
        return $this->meta['from'] ?? null;
    }

    /**
     * Get the last item index on this page.
     */
    public function to(): ?int
    {
        return $this->meta['to'] ?? null;
    }

    /**
     * Get the URL for a given page.
     */
    public function url(int $page): ?string
    {
        if ($page === 1 && isset($this->links['first'])) {
            return $this->links['first'];
        }

        if ($page === $this->lastPage() && isset($this->links['last'])) {
            return $this->links['last'];
        }

        return null;
    }

    /**
     * Get the URL for the next page.
     */
    public function nextPageUrl(): ?string
    {
        return $this->links['next'] ?? null;
    }

    /**
     * Get the URL for the previous page.
     */
    public function previousPageUrl(): ?string
    {
        return $this->links['prev'] ?? null;
    }

    /**
     * Determine if more pages exist after the current page.
     */
    public function hasMorePages(): bool
    {
        return $this->nextPageUrl() !== null ||
               ($this->currentPage() !== null &&
                $this->lastPage() !== null &&
                $this->currentPage() < $this->lastPage());
    }

    /**
     * Alias for hasMorePages
     */
    public function hasMore(): bool
    {
        return $this->hasMorePages();
    }

    /**
     * Get the items for the current page.
     */
    public function items(): array
    {
        return $this->data;
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
            'links' => $this->links,
        ];
    }
}
