<?php

declare(strict_types=1);

namespace Core\User\Domain\Contracts;

use Core\Auth\Domain\Enums\UserRole;
use Core\Auth\Domain\Enums\UserStatus;
use Core\User\Domain\DataObjects\UserData;
use Core\User\Domain\Entities\UserEntity;
use Core\Shared\Domain\DataObjects\PaginatedResult;

/**
 * Persistence interface for the User module. Application code depends
 * on this contract, keeping infrastructure details like Eloquent out of the core.
 */
interface UserRepositoryContract
{
    /**
     * Creates a new user and returns it as a Domain Entity.
     *
     * @param UserData $userData
     *
     * @return UserEntity
     */
    public function create(UserData $userData): UserEntity;

    /**
     * Returns a paginated list of users, optionally restricted to one role.
     *
     * @param int           $perPage    Items per page (default: 10).
     * @param UserRole|null $roleFilter Restrict results to this role; null returns all roles.
     *
     * @return PaginatedResult
     */
    public function paginate(int $perPage = 10, ?UserRole $roleFilter = null): PaginatedResult;

    /**
     * Finds a user by ID.
     *
     * @param int $id The user ID.
     *
     * @return UserEntity|null Null when not found.
     */
    public function findById(int $id): ?UserEntity;

    /**
     * Finds a user by ID with row-level locking (SELECT ... FOR UPDATE).
     *
     * @param int $id The user ID.
     *
     * @return UserEntity|null Null when not found.
     */
    public function findByIdForUpdate(int $id): ?UserEntity;

    /**
     * Updates a user by ID.
     *
     * @param int    $id       The user ID.
     * @param UserData $userData The updated data.
     *
     * @return UserEntity|null Null when not found.
     */
    public function update(int $id, UserData $userData): ?UserEntity;

    /**
     * Updates only the account status of a user.
     *
     * @param int        $id     The user ID.
     * @param UserStatus $status The new account status.
     *
     * @return UserEntity|null Null when not found.
     */
    public function updateStatus(int $id, UserStatus $status): ?UserEntity;

    /**
     * Deletes a user by ID.
     *
     * @param int $id The user ID.
     *
     * @return bool True on success, false on failure.
     */
    public function delete(int $id): bool;
}
