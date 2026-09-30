<?php

declare(strict_types=1);

namespace Core\User\Application\DTOs\Requests;

use Core\Auth\Domain\Enums\UserRole;
use Core\Auth\Domain\Enums\UserStatus;

/**
 * Immutable data transfer object for creating a user.
 */
readonly class UserCreateDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public mixed $image = null,
        public UserRole $role = UserRole::MEMBER,
        public UserStatus $status = UserStatus::PENDING,
    ) {}

    /**
     * Returns a new instance with the image set.
     */
    public function withImage(mixed $image): self
    {
        return new self(
            name: $this->name,
            email: $this->email,
            password: $this->password,
            image: $image,
            role: $this->role,
            status: $this->status,
        );
    }
}
