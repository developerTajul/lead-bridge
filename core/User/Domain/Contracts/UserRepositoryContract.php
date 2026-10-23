<?php

declare(strict_types=1);

namespace Core\User\Domain\Contracts;

use Core\Auth\Domain\Enums\AuthRole;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Core\User\Domain\Entities\UserEntity;

/**
 * PORT: persistence for user accounts.
 *
 * Takes and returns Entities only. The write side reads and writes accounts
 * through this contract; the read side has its own port and read model.
 */
interface UserRepositoryContract
{
    /**
     * Stores a new user and returns it as a Domain Entity.
     *
     * @param UserEntity $user The unsaved user.
     *
     * @return UserEntity The stored user, carrying the assigned id.
     */
    public function create(UserEntity $user): UserEntity;

    /**
     * Stores changes to an existing user.
     *
     * @param UserEntity $user The user to write, carrying its id.
     *
     * @return UserEntity The stored user.
     */
    public function update(UserEntity $user): UserEntity;

    /**
     * Stores a change to the user's account status alone.
     *
     * @param UserEntity $user Carries the account whose status changes.
     *
     * @return UserEntity The stored user.
     */
    public function updateStatus(UserEntity $user): UserEntity;

    /**
     * Finds a user by email.
     *
     * @param string $email The user email.
     *
     * @return UserEntity|null Null when not found.
     */
    public function findByEmail(string $email): ?UserEntity;

    /**
     * Finds a user by ID.
     *
     * @param int $id The user ID.
     *
     * @return UserEntity|null Null when not found.
     */
    public function findById(int $id): ?UserEntity;

    /**
     * Finds a user by ID while holding a row lock, for use cases that read a
     * user and then write it back inside a transaction.
     *
     * @param int $id The user ID.
     *
     * @return UserEntity|null Null when not found.
     */
    public function findByIdForUpdate(int $id): ?UserEntity;

    /**
     * Returns a paginated list of users, optionally restricted to one role.
     *
     * @param int           $perPage    Items per page (default: 10).
     * @param AuthRole|null $roleFilter Restrict results to this role; null returns all roles.
     *
     * @return PaginatedResult Items are UserEntity instances.
     */
    public function paginate(int $perPage = 10, ?AuthRole $roleFilter = null): PaginatedResult;

    /**
     * Deletes a user by ID.
     *
     * @param int $id The user ID.
     *
     * @return bool True on success, false on failure.
     */
    public function delete(int $id): bool;
}
