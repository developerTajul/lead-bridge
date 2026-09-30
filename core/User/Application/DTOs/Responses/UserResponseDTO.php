<?php

declare(strict_types=1);

namespace Core\User\Application\DTOs\Responses;

/**
 * Response DTO for a user resource.
 */
readonly class UserResponseDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $image,
        public string $role,
        public string $status,
    ) {}
}
