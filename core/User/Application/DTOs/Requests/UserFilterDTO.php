<?php

declare(strict_types=1);

namespace Core\User\Application\DTOs\Requests;

/**
 * Carries validated delivery-layer query input INTO the Service. Immutable.
 */
final readonly class UserFilterDTO
{
    /**
     * @param int $perPage
     */
    public function __construct(
        public int $perPage = 15,
    ) {}
}
