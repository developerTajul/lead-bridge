<?php

declare(strict_types=1);

namespace Core\User\Domain\Entities;

use Core\Auth\Domain\Enums\UserRole;
use Core\Auth\Domain\Enums\UserStatus;

/**
 * Pure domain entity representing a user.
 */
readonly class UserEntity
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $emailVerifiedAt,
        public string $password,
        public ?string $image,
        public UserRole $role,
        public UserStatus $status,
        public ?string $rememberToken,
        public string $createdAt,
        public string $updatedAt,
        public ?string $deletedAt,
    ) {}
}
