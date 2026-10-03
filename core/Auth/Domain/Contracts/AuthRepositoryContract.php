<?php

declare(strict_types=1);

namespace Core\Auth\Domain\Contracts;

use Core\Auth\Domain\DataObjects\AuthRegisterData;
use Core\Auth\Domain\Entities\User;

/**
 * Persistence port for auth users.
 */
interface AuthRepositoryContract
{
    /**
     * Persists a new user and returns it as a Domain Entity.
     *
     * @param AuthRegisterData $userData
     *
     * @return User
     */
    public function save(AuthRegisterData $userData): User;

    /**
     * Finds a user by email address.
     *
     * @param string $email
     *
     * @return User|null Null when no user matches.
     */
    public function findByEmail(string $email): ?User;


    /**
     * Finds a user by id.
     *
     * @param int $userId
     *
     * @return User|null Null when no user matches.
     */
    public function findById(int $userId): ?User;
}
