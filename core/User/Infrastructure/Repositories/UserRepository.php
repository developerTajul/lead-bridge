<?php

declare(strict_types=1);

namespace Core\User\Infrastructure\Repositories;

use App\Models\User;
use Core\Auth\Domain\Enums\AuthRole;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Core\Shared\Infrastructure\Repositories\BaseRepository;
use Core\User\Domain\Contracts\UserRepositoryContract;
use Core\User\Domain\Entities\UserEntity;
use Core\User\Infrastructure\Mappers\UserEntityMapper;

/**
 * Eloquent-backed persistence for users. The only place in the module
 * where Laravel/Eloquent is used; results are mapped to Domain Entities first.
 *
 * @implements UserRepositoryContract
 */
final class UserRepository extends BaseRepository implements UserRepositoryContract
{
    /**
     * @param User             $model
     * @param UserEntityMapper $mapper
     */
    public function __construct(
        User $model,
        private readonly UserEntityMapper $mapper,
    ) {
        parent::__construct($model);
    }

    /**
     * Stores a new user and returns it with the id the table assigned.
     *
     * @param UserEntity $user
     *
     * @return UserEntity
     */
    public function create(UserEntity $user): UserEntity
    {
        $userModel = $this->createRecord($this->mapper->toPersistenceArray($user));

        return $this->mapper->toEntity($this->rawColumns($userModel));
    }

    /**
     * Stores changes to an existing user. The row is found by its id, so the
     * entity's own id decides which row is written.
     *
     * @param UserEntity $user
     *
     * @return UserEntity The stored user.
     */
    public function update(UserEntity $user): UserEntity
    {
        $userModel = $this->updateRecord((int) $user->id, $this->mapper->toPersistenceArray($user));

        if ($userModel === null) {
            return $user;
        }

        return $this->mapper->toEntity($this->rawColumns($userModel));
    }

    /**
     * Stores a change to the user's account status alone, leaving the rest of
     * the row as it stands.
     *
     * @param UserEntity $user Carries the status to store.
     *
     * @return UserEntity The stored user.
     */
    public function updateStatus(UserEntity $user): UserEntity
    {
        $userModel = $this->updateRecord((int) $user->id, ['status' => $user->status->value]);

        if ($userModel === null) {
            return $user;
        }

        return $this->mapper->toEntity($this->rawColumns($userModel));
    }

    /**
     * Finds a user by email.
     *
     * @param string $email The user email.
     *
     * @return UserEntity|null Null when not found.
     */
    public function findByEmail(string $email): ?UserEntity
    {
        $userModel = $this->model->where('email', $email)->first();

        if (! $userModel instanceof User) {
            return null;
        }

        return $this->mapper->toEntity($this->rawColumns($userModel));
    }

    /**
     * Finds a user by ID.
     *
     * @param int $id The user ID.
     *
     * @return UserEntity|null Null when not found.
     */
    public function findById(int $id): ?UserEntity
    {
        $userModel = $this->findRecord($id);

        if (! $userModel instanceof User) {
            return null;
        }

        return $this->mapper->toEntity($this->rawColumns($userModel));
    }

    /**
     * Finds a user by ID with row-level locking (SELECT ... FOR UPDATE).
     *
     * @param int $id The user ID.
     *
     * @return UserEntity|null Null when not found.
     */
    public function findByIdForUpdate(int $id): ?UserEntity
    {
        $userModel = $this->findRecordForUpdate($id);

        if (! $userModel instanceof User) {
            return null;
        }

        return $this->mapper->toEntity($this->rawColumns($userModel));
    }

    /**
     * @param int           $perPage    Items per page (default: 10).
     * @param AuthRole|null $roleFilter Restrict results to this role; null returns all roles.
     *
     * @return PaginatedResult Items are UserEntity instances.
     */
    public function paginate(int $perPage = 10, ?AuthRole $roleFilter = null): PaginatedResult
    {
        $query = $this->model->latest();

        if ($roleFilter !== null) {
            $query->where('role', $roleFilter->value);
        }

        $paginator = $query->paginate($perPage);

        $entities = $paginator
            ->getCollection()
            ->map(fn(User $item) => $this->mapper->toEntity($this->rawColumns($item)))
            ->all();

        return new PaginatedResult(
            items: $entities,
            total: $paginator->total(),
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            lastPage: $paginator->lastPage(),
        );
    }

    /**
     * Deletes a user by ID.
     *
     * @param int $id The user ID.
     *
     * @return bool True on success, false on failure.
     */
    public function delete(int $id): bool
    {
        return $this->deleteRecord($id);
    }

    /**
     * Returns a model's raw column values for the EntityMapper.
     *
     * getAttributes() is used rather than toArray(): toArray() applies the
     * model's $hidden list and its casts, which would drop the password column
     * the login use case needs and rewrite the timestamps.
     *
     * @param User $userModel
     *
     * @return array<string, mixed>
     */
    private function rawColumns(User $userModel): array
    {
        return $userModel->getAttributes();
    }
}
