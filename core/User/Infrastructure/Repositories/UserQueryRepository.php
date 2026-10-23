<?php

declare(strict_types=1);

namespace Core\User\Infrastructure\Repositories;

use App\Models\User as UserModel;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Core\User\Domain\Contracts\UserQueryContract;
use Core\User\Domain\DataObjects\StoredUserData;
use Core\User\Domain\ValueObjects\UserFilter;

/**
 * Eloquent-backed reads for the user list and detail.
 *
 * Builds read models rather than the Auth module's Domain Entity: these reads
 * answer listing questions, and the raw column values are all they need. The
 * password hash is never selected.
 *
 * @implements UserQueryContract
 */
final class UserQueryRepository implements UserQueryContract
{
    /**
     * The columns a user read is allowed to touch. Naming them keeps the hash
     * out of the result rather than stripping it afterwards.
     */
    private const READ_COLUMNS = ['id', 'name', 'email', 'image', 'role', 'status', 'created_at'];

    /**
     * Returns the users matching the given filter criteria.
     *
     * @param UserFilter $filter
     *
     * @return PaginatedResult Items are StoredUserData instances.
     */
    public function getFilteredUsers(UserFilter $filter): PaginatedResult
    {
        $eloquentPaginator = UserModel::query()
            ->select(self::READ_COLUMNS)
            // The id tie-breaks rows sharing a created_at. Without it the order
            // of equal keys is undefined, so a paginated offset can repeat a row
            // on one page and skip another.
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($filter->perPage);

        $users = $eloquentPaginator
            ->getCollection()
            ->map(fn(UserModel $user) => $this->toStoredUserData($user))
            ->all();

        return new PaginatedResult(
            items: $users,
            total: $eloquentPaginator->total(),
            currentPage: $eloquentPaginator->currentPage(),
            perPage: $eloquentPaginator->perPage(),
            lastPage: $eloquentPaginator->lastPage(),
        );
    }

    /**
     * Returns one user by id.
     *
     * @param int $id
     *
     * @return StoredUserData|null Null when no user carries the id.
     */
    public function findUserById(int $id): ?StoredUserData
    {
        $user = UserModel::query()
            ->select(self::READ_COLUMNS)
            ->find($id);

        if (! $user instanceof UserModel) {
            return null;
        }

        return $this->toStoredUserData($user);
    }

    /**
     * @param UserModel $user
     *
     * @return StoredUserData
     */
    private function toStoredUserData(UserModel $user): StoredUserData
    {
        return new StoredUserData(
            id: (int) $user->id,
            name: (string) $user->name,
            email: (string) $user->email,
            image: $user->image === null ? null : (string) $user->image,
            role: (string) $user->role,
            status: (string) $user->status,
            createdAt: $user->created_at === null ? null : (string) $user->created_at,
        );
    }
}
