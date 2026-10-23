<?php

declare(strict_types=1);

namespace Core\User\Domain\Contracts;

use Core\Shared\Domain\DataObjects\PaginatedResult;
use Core\User\Domain\DataObjects\StoredUserData;
use Core\User\Domain\ValueObjects\UserFilter;

/**
 * Read side of the user persistence port.
 */
interface UserQueryContract
{
    /**
     * Returns the users matching the given filter criteria.
     *
     * @param UserFilter $filter
     *
     * @return PaginatedResult Items are StoredUserData instances.
     */
    public function getFilteredUsers(UserFilter $filter): PaginatedResult;

    /**
     * Returns one user by id.
     *
     * @param int $id
     *
     * @return StoredUserData|null Null when no user carries the id.
     */
    public function findUserById(int $id): ?StoredUserData;
}
