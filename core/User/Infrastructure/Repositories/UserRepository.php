<?php

declare(strict_types=1);

namespace Core\User\Infrastructure\Repositories;

use App\Models\User;
use Core\Auth\Domain\Enums\UserRole;
use Core\Auth\Domain\Enums\UserStatus;
use Core\User\Domain\Contracts\UserRepositoryContract;
use Core\User\Domain\DataObjects\UserData;
use Core\User\Domain\Entities\UserEntity;
use Core\User\Infrastructure\Mappers\UserEntityMapper;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Core\Shared\Infrastructure\Repositories\BaseRepository;

/**
 * Eloquent-backed persistence for users. The only place in the module
 * where Laravel/Eloquent is used; results are mapped to Domain Entities first.
 *
 * @implements \Core\User\Domain\Contracts\UserRepositoryContract
 */
final class UserRepository extends BaseRepository implements UserRepositoryContract
{
    /**
     * @param User               $model
     * @param UserEntityMapper   $mapper
     */
    public function __construct(
        User $model,
        private readonly UserEntityMapper $mapper,
    ) {
        parent::__construct($model);
    }

    /**
     * @param UserData $userData
     *
     * @return UserEntity
     */
    public function create(UserData $userData): UserEntity
    {
        $userModel = $this->createRecord($userData->toArray());

        return $this->mapper->toEntity($userModel->toArray());
    }

    /**
     * @param string $email The user email.
     *
     * @return UserEntity|null Null when not found.
     */
    public function findByEmail(string $email): ?UserEntity
    {
        $userModel = $this->model->where('email', $email)->first();

        if (!$userModel) {
            return null;
        }

        // toArray() honours $hidden, which excludes the password — the
        // login use case needs it, so clear the hidden list first.
        return $this->mapper->toEntity($userModel->setHidden([])->toArray());
    }

    /**
     * @param int $id The user ID.
     *
     * @return UserEntity|null Null when not found.
     */
    public function findById(int $id): ?UserEntity
    {
        $userModel = $this->findRecord($id);

        if (!$userModel) {
            return null;
        }

        return $this->mapper->toEntity($userModel->toArray());
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

        if (!$userModel) {
            return null;
        }

        return $this->mapper->toEntity($userModel->toArray());
    }

    /**
     * @param int      $id       The user ID.
     * @param UserData $userData The updated data.
     *
     * @return UserEntity|null Null when not found.
     */
    public function update(int $id, UserData $userData): ?UserEntity
    {
        $userModel = $this->updateRecord($id, $userData->toArray());

        if ($userModel === null) {
            return null;
        }

        return $this->mapper->toEntity($userModel->toArray());
    }

    /**
     * @param int        $id     The user ID.
     * @param UserStatus $status The new account status.
     *
     * @return UserEntity|null Null when not found.
     */
    public function updateStatus(int $id, UserStatus $status): ?UserEntity
    {
        $userModel = $this->updateRecord($id, ['status' => $status->value]);

        if ($userModel === null) {
            return null;
        }

        return $this->mapper->toEntity($userModel->toArray());
    }

    /**
     * @param int $id The user ID.
     *
     * @return bool True on success, false on failure.
     */
    public function delete(int $id): bool
    {
        return $this->deleteRecord($id);
    }

    /**
     * @param int           $perPage    Items per page (default: 10).
     * @param UserRole|null $roleFilter Restrict results to this role; null returns all roles.
     *
     * @return PaginatedResult
     */
    public function paginate(int $perPage = 10, ?UserRole $roleFilter = null): PaginatedResult
    {
        $query = $this->model->latest();

        if ($roleFilter !== null) {
            $query->where('role', $roleFilter->value);
        }

        $paginator = $query->paginate($perPage);

        $entities = $paginator
            ->getCollection()
            ->map(fn(User $item) => $this->mapper->toEntity($item->toArray()))
            ->all();

        return new PaginatedResult(
            items: $entities,
            total: $paginator->total(),
            currentPage: $paginator->currentPage(),
            perPage: $paginator->perPage(),
            lastPage: $paginator->lastPage(),
        );
    }
}
