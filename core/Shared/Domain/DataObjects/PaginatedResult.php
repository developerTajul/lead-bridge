<?php

declare(strict_types=1);

namespace Core\Shared\Domain\DataObjects;

/**
 * Framework-free pagination payload.
 * Created by Infrastructure repositories, consumed by Application services
 * and returned to Delivery inside a Result envelope.
 */
readonly class PaginatedResult
{
    public function __construct(
        public array $items,
        public int $total,
        public int $currentPage,
        public int $perPage,
        public int $lastPage,
    ) {}

    /**
     * Map items through a callable, returning a new PaginatedResult with
     * transformed items. Used by the Service layer to convert Domain Entities
     * into Response DTOs without mutating state.
     *
     * @param callable $callback
     * @return self
     */
    public function map(callable $callback): self
    {
        return new self(
            items: array_map($callback, $this->items),
            total: $this->total,
            currentPage: $this->currentPage,
            perPage: $this->perPage,
            lastPage: $this->lastPage,
        );
    }
}
