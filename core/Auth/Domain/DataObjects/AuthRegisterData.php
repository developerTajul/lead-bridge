<?php

declare(strict_types=1);

namespace Core\Auth\Domain\DataObjects;

use Core\Auth\Domain\Enums\AuthRole;
use Core\Auth\Domain\Enums\AuthStatus;

/**
 * Immutable data object carrying auth registration data into persistence.
 */
readonly class AuthRegisterData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public AuthRole $role = AuthRole::MEMBER,
        public AuthStatus $status = AuthStatus::PENDING,
    ) {}

    public function toArray(): array
    {
        $data = [
            'name'            => $this->name,
            'email'           => $this->email,
            'password'        => $this->password,
            'role'            => $this->role->value,
            'status'          => $this->status->value
        ];

        return array_filter($data, fn($value) => $value !== null);
    }
}
