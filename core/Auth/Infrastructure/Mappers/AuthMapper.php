<?php

declare(strict_types=1);

namespace Core\Auth\Infrastructure\Mappers;

use App\Models\User as UserModel;
use Core\Auth\Domain\Entities\User;
use Core\Auth\Domain\Enums\AuthRole;
use Core\Auth\Domain\Enums\AuthStatus;

/**
 * Maps model arrays to User entities.
 */
final class AuthMapper
{
    public function toEntity(UserModel $model): User
    {
        return new User(
            id: (int) $model->id,
            name: (string) $model->name,
            email: (string) $model->email,
            password: (string) $model->password,
            role: AuthRole::tryFrom((string) ($model['role'] ?? 'MEMBER')) ?? AuthRole::MEMBER,
            status: AuthStatus::tryFrom((string) ($model['status'] ?? 'PENDING')) ?? AuthStatus::PENDING
        );
    }
}
