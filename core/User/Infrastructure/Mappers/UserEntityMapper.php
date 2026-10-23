<?php

declare(strict_types=1);

namespace Core\User\Infrastructure\Mappers;

use Core\Auth\Domain\Enums\AuthRole;
use Core\Auth\Domain\Enums\AuthStatus;
use Core\User\Domain\Entities\UserEntity;

/**
 * Maps model arrays to UserEntity and back to the users table's columns.
 */
final class UserEntityMapper
{
    /**
     * Rebuilds a user from a row's raw column values.
     *
     * The raw values are passed straight through: the model's own casts and its
     * $hidden list would strip the password this mapper has to carry.
     *
     * @param array $data
     *
     * @return UserEntity
     */
    public function toEntity(array $data): UserEntity
    {
        return UserEntity::reconstitute(
            id: (int) $data['id'],
            name: (string) $data['name'],
            email: (string) $data['email'],
            emailVerifiedAt: $data['email_verified_at'] ?? null,
            password: (string) $data['password'],
            image: $data['image'] ?? null,
            role: AuthRole::from((string) $data['role']),
            status: AuthStatus::from((string) $data['status']),
            rememberToken: $data['remember_token'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            deletedAt: $data['deleted_at'] ?? null,
        );
    }

    /**
     * Flattens the entity's own state onto the users table's columns.
     *
     * The password is written as it stands. The model's "hashed" cast leaves a
     * value that is already a hash untouched, so what the entity carries is what
     * the column receives.
     *
     * @param UserEntity $user
     *
     * @return array<string, mixed>
     */
    public function toPersistenceArray(UserEntity $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->emailVerifiedAt,
            'password' => $user->password,
            'image' => $user->image,
            'role' => $user->role->value,
            'status' => $user->status->value,
            'remember_token' => $user->rememberToken,
        ];
    }
}
