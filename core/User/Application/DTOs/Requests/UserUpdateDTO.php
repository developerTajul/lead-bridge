<?php

declare(strict_types=1);

namespace Core\User\Application\DTOs\Requests;

use Core\Auth\Domain\Enums\UserRole;
use Core\Auth\Domain\Enums\UserStatus;

/**
 * Immutable data transfer object for updating a user.
 */
readonly class UserUpdateDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public mixed $password = null,
        public mixed $image = null,
        public UserRole $role,
        public UserStatus $status,
    ) {}

    /**
     * Returns a new instance with the image set.
     */
    public function withImage(mixed $image): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            password: $this->password,
            image: $image,
            role: $this->role,
            status: $this->status,
        );
    }
}
