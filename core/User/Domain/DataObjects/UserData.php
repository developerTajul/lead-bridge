<?php

declare(strict_types=1);

namespace Core\User\Domain\DataObjects;

use Core\Auth\Domain\Enums\AuthRole;
use Core\Auth\Domain\Enums\AuthStatus;

/**
 * Immutable data object for creating or updating a user.
 */
readonly class UserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $image = null,
        public AuthRole $role = AuthRole::MEMBER,
        public AuthStatus $status = AuthStatus::PENDING,
    ) {}

    /**
     * Returns the data as an associative array suitable for database insertion/update.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'image' => $this->image,
            'role' => $this->role->value,
            'status' => $this->status->value,
        ];

        return array_filter($data, fn($value) => $value !== null);
    }
}
