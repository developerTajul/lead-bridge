<?php

declare(strict_types=1);

namespace Core\Auth\Infrastructure\Repositories;

use App\Models\User as UserModel;
use Core\Auth\Domain\Contracts\AuthRepositoryContract;
use Core\Auth\Domain\DataObjects\AuthRegisterData;
use Core\Auth\Domain\Entities\User;
use Core\Auth\Infrastructure\Mappers\AuthMapper;

/**
 * Eloquent‑backed persistence for auth users.
 *
 * @implements AuthRepositoryContract
 */
final class AuthRepository implements AuthRepositoryContract
{
    public function __construct(
        private readonly AuthMapper $mapper,
    ) {}

    public function save(AuthRegisterData $userData): User
    {
        $model = UserModel::create($userData->toArray());
        return $this->mapper->toEntity($model);
    }

    public function findByEmail(string $email): ?User
    {
        $model = UserModel::where('email', $email)->first();
        if (!$model) {
            return null;
        }
        return $this->mapper->toEntity($model);
    }


    public function findById(int $userId): ?User
    {
        $model = UserModel::find($userId);
        if (!$model) {
            return null;
        }
        return $this->mapper->toEntity($model);
    }




}
