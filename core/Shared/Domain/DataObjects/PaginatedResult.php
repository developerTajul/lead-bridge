<?php

declare(strict_types=1);

namespace Core\Shared\Domain\DataObjects;

/**
 * Framework-free pagination payload.
 * Created by Infrastructure repositories, consumed by Application services
 * and returned to Delivery inside a Result envelope.
 */
final readonly class PaginatedResult
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


    /**
     * Static factory method to create an empty pagination result.
     * This removes the burden of knowing the structure from the Controller.
     */
    public static function empty(int $perPage = 15): self
    {
        return new self(
            items: [],
            total: 0,
            currentPage: 1,
            perPage: $perPage,
            lastPage: 1,
        );
    }
}
