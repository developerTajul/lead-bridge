<?php

declare(strict_types=1);

namespace Core\Auth\Domain\Entities;

use Core\Auth\Domain\Enums\AuthRole;
use Core\Auth\Domain\Enums\AuthStatus;

/**
 * Pure domain entity representing an authenticatable user.
 */
readonly class User
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $password,
        public AuthRole $role,
        public AuthStatus $status
    ) {}
}
