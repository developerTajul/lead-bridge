<?php

declare(strict_types=1);

namespace Core\Activity\Application\DTOs\Requests;

readonly class ActivityFilterDTO
{
    public function __construct(
        public ?string $status = null,
        public ?string $type = null,
        public ?int $userId = null,
        public ?string $filter = null, // today, overdue ইত্যাদি
        public int $perPage = 15
    ) {}
}