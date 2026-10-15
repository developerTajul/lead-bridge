<?php

declare(strict_types=1);

namespace Core\User\Infrastructure\Mappers;

use Core\Auth\Domain\Enums\AuthRole;
use Core\Auth\Domain\Enums\AuthStatus;
use Core\User\Domain\Entities\UserEntity;

/** Maps model arrays to UserEntity. */
final class UserEntityMapper
{
    public function toEntity(array $data): UserEntity
    {
        return new UserEntity(
            id: (int) $data['id'],
            name: (string) $data['name'],
            email: (string) $data['email'],
            emailVerifiedAt: $data['email_verified_at'] ?? null,
            password: (string) ($data['password'] ?? ''),
            image: $data['image'] ?? null,
            role: AuthRole::tryFrom((string) $data['role']) ?? AuthRole::MEMBER,
            status: AuthStatus::tryFrom((string) $data['status']) ?? AuthStatus::PENDING,
            rememberToken: $data['remember_token'] ?? null,
            createdAt: (string) $data['created_at'],
            updatedAt: (string) $data['updated_at'],
            deletedAt: $data['deleted_at'] ?? null,
        );
    }
}
