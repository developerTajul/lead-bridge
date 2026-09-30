<?php

declare(strict_types=1);

namespace Core\User\Domain\DataObjects;

use Core\Auth\Domain\Enums\UserRole;
use Core\Auth\Domain\Enums\UserStatus;

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
        public UserRole $role = UserRole::MEMBER,
        public UserStatus $status = UserStatus::PENDING,
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
