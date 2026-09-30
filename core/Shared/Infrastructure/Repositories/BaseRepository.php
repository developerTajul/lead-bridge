<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Shared Eloquent CRUD base for module repositories. Infrastructure only —
 * never leaks framework types upward; module repositories map results to
 * Domain Entities via their EntityMapper before returning.
 */
abstract class BaseRepository
{
    public function __construct(
        protected readonly Model $model,
    ) {}

    protected function fetchAllLatest(): Collection
    {
        return $this->model->latest()->get();
    }

    protected function findRecord(int $id): ?Model
    {
        return $this->model->find($id);
    }

    protected function findRecordWithRelations(int $id, array $relations): ?Model
    {
        return $this->model->with($relations)->find($id);
    }

    protected function findRecordForUpdate(int $id): ?Model
    {
        return $this->model->lockForUpdate()->find($id);
    }

    protected function createRecord(array $data): Model
    {
        return $this->model->create($data);
    }

    protected function updateRecord(int $id, array $data): ?Model
    {
        $record = $this->model->find($id);

        if ($record === null) {
            return null;
        }

        $record->fill($data);

        if ($record->isDirty()) {
            $record->save();
        }

        return $record;
    }

    protected function deleteRecord(int $id): bool
    {
        return $this->model->destroy($id) > 0;
    }

    protected function paginateEloquent(int $perPage = 10): LengthAwarePaginator
    {
        return $this->model->latest()->paginate($perPage);
    }
}
