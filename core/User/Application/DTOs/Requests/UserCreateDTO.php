<?php

declare(strict_types=1);

namespace Core\User\Application\DTOs\Requests;

use Core\Auth\Domain\Enums\AuthRole;
use Core\Auth\Domain\Enums\AuthStatus;

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
        public AuthRole $role = AuthRole::MEMBER,
        public AuthStatus $status = AuthStatus::PENDING,
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
