<?php

declare(strict_types=1);

namespace Core\Deal\Infrastructure\Repositories;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Pipeline;
use Core\Deal\Domain\Contracts\DealQueryContract;
use Core\Deal\Domain\Contracts\DealRepositoryContract;
use Core\Deal\Domain\DataObjects\StoredDealData;
use Core\Deal\Domain\Entities\DealEntity;
use Core\Deal\Domain\ValueObjects\DealFilter;
use Core\Deal\Infrastructure\Mappers\DealEntityMapper;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Core\Shared\Infrastructure\Repositories\BaseRepository;
use RuntimeException;

/**
 * Eloquent-backed persistence for deals. The only place in the module where
 * Laravel/Eloquent is used; results are mapped to Domain Entities first.
 *
 * @implements DealRepositoryContract
 * @implements DealQueryContract
 */
final class DealEloquentRepository extends BaseRepository implements DealRepositoryContract, DealQueryContract
{
    /**
     * @param Deal            $model
     * @param DealEntityMapper $mapper
     */
    public function __construct(
        Deal $model,
        private readonly DealEntityMapper $mapper,
    ) {
        parent::__construct($model);
    }

    /**
     * Persists the deal and returns it as stored, so the caller reads back the
     * identifier and timestamps the write assigned.
     *
     * @param DealEntity $deal
     *
     * @return DealEntity
     *
     * @throws RuntimeException When the deal row could not be written.
     */
    public function save(DealEntity $deal): DealEntity
    {
        $dealModel = $this->createRecord($this->mapper->toPersistenceArray($deal));

        if (! $dealModel instanceof Deal) {
            throw new RuntimeException('The deal row could not be written.');
        }

        // Read back through a fresh query so the returned entity carries the
        // columns the database filled in, not the values that were sent.
        $storedModel = $this->findRecord($dealModel->id);

        if (! $storedModel instanceof Deal) {
            throw new RuntimeException('The deal row could not be read back.');
        }

        return $this->mapper->toEntity($storedModel->getAttributes());
    }

    /**
     * Reads a deal while holding a row lock, so a concurrent write cannot slip
     * between the read and the save.
     *
     * @param int $id
     *
     * @return DealEntity|null Null when no deal carries the id.
     */
    public function findByIdWithLock(int $id): ?DealEntity
    {
        $dealModel = $this->findRecordForUpdate($id);

        if (! $dealModel instanceof Deal) {
            return null;
        }

        return $this->mapper->toEntity($dealModel->toArray());
    }

    /**
     * Persists changes to an existing deal and reads the row back, so the
     * returned entity carries the timestamp the write assigned.
     *
     * @param DealEntity $deal
     *
     * @return DealEntity
     *
     * @throws RuntimeException When the entity has no ID, or the row no longer exists.
     */
    public function update(DealEntity $deal): DealEntity
    {
        if ($deal->id === null) {
            throw new RuntimeException('Cannot update a deal that has no ID.');
        }

        $storedModel = $this->updateRecord($deal->id, $this->mapper->toPersistenceArray($deal));

        if (! $storedModel instanceof Deal) {
            throw new RuntimeException(sprintf(
                'Deal with ID %d no longer exists.',
                $deal->id,
            ));
        }

        return $this->mapper->toEntity($storedModel->toArray());
    }

    /**
     * Deletes a deal.
     *
     * @param DealEntity $deal
     *
     * @return void
     *
     * @throws RuntimeException When the entity has no ID, or the row could not be deleted.
     */
    public function delete(DealEntity $deal): void
    {
        if ($deal->id === null) {
            throw new RuntimeException('Cannot delete a deal that has no ID.');
        }

        $deleted = $this->deleteRecord($deal->id);

        if (! $deleted) {
            throw new RuntimeException(sprintf(
                'Deal with ID %d could not be deleted.',
                $deal->id,
            ));
        }
    }

    /**
     * The name of a stage, or null when no stage carries the id.
     *
     * @param int $dealStageId
     *
     * @return string|null
     */
    public function findStageNameById(int $dealStageId): ?string
    {
        $stageName = DealStage::query()->whereKey($dealStageId)->value('name');

        if ($stageName === null) {
            return null;
        }

        return (string) $stageName;
    }

    /**
     * Returns the deals matching the given filter criteria.
     *
     * @param DealFilter $filter
     *
     * @return PaginatedResult Items are StoredDealData instances.
     */
    public function getFilteredDeals(DealFilter $filter): PaginatedResult
    {
        $query = Deal::query();

        if ($filter->pipelineId !== null) {
            $query->where('pipeline_id', $filter->pipelineId);
        }

        if ($filter->dealStageId !== null) {
            $query->where('deal_stage_id', $filter->dealStageId);
        }

        // The relations are narrowed to the columns the response prints, so a
        // list read never loads a whole record for a name.
        $eloquentPaginator = $query
            ->with(['company:id,name', 'contact:id,name', 'pipeline:id,name', 'stage:id,name,probability,order_position'])
            // The id tie-breaks rows sharing a created_at. Without it the order
            // of equal keys is undefined, so a paginated offset can repeat a row
            // on one page and skip another.
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($filter->perPage);

        $deals = $eloquentPaginator
            ->getCollection()
            ->map(fn(Deal $item) => $this->toStoredDealData($item))
            ->all();

        return new PaginatedResult(
            items: $deals,
            total: $eloquentPaginator->total(),
            currentPage: $eloquentPaginator->currentPage(),
            perPage: $eloquentPaginator->perPage(),
            lastPage: $eloquentPaginator->lastPage(),
        );
    }

    /**
     * Returns one deal with the records it points at and the activities logged
     * against it.
     *
     * @param int $id
     *
     * @return StoredDealData|null Null when no deal carries the id.
     */
    public function findDetailById(int $id): ?StoredDealData
    {
        $dealModel = $this->findRecordWithRelations($id, [
            'company:id,name',
            'contact:id,name',
            'pipeline:id,name,is_default',
            'stage:id,name,probability,order_position',
            'activities.user',
        ]);

        if (! $dealModel instanceof Deal) {
            return null;
        }

        // The entity is built from the deal's own attributes, before the loaded
        // relations are read, so its timestamps stay the deal's own.
        return new StoredDealData(
            deal: $this->mapper->toEntity($dealModel->getAttributes()),
            company: $this->toRelationRow($dealModel->company),
            contact: $this->toRelationRow($dealModel->contact),
            pipeline: $this->toRelationRow($dealModel->pipeline),
            stage: $this->toRelationRow($dealModel->stage),
            activities: $this->toActivityRows($dealModel),
        );
    }

    /**
     * Flattens a deal and the relations a list read carries.
     *
     * @param Deal $dealModel
     *
     * @return StoredDealData
     */
    private function toStoredDealData(Deal $dealModel): StoredDealData
    {
        return new StoredDealData(
            deal: $this->mapper->toEntity($dealModel->getAttributes()),
            company: $this->toRelationRow($dealModel->company),
            contact: $this->toRelationRow($dealModel->contact),
            pipeline: $this->toRelationRow($dealModel->pipeline),
            stage: $this->toRelationRow($dealModel->stage),
        );
    }

    /**
     * Flattens a related row to its columns, so nothing above Infrastructure
     * has to know how Eloquent nests a relation.
     *
     * @param mixed $relation
     *
     * @return array<string, mixed>|null Null when the row was not loaded or is absent.
     */
    private function toRelationRow(mixed $relation): ?array
    {
        if ($relation instanceof Company || $relation instanceof Contact
            || $relation instanceof Pipeline || $relation instanceof DealStage) {
            return $relation->getAttributes();
        }

        return null;
    }

    /**
     * Flattens the activities logged against a deal. The casts already turn the
     * timestamps into date objects, so only their string form is left to take.
     *
     * @param Deal $dealModel
     *
     * @return array<int, array<string, mixed>>
     */
    private function toActivityRows(Deal $dealModel): array
    {
        return $dealModel->activities
            ->map(fn(Activity $activity) => [
                'id' => $activity->id,
                'type' => $activity->type,
                'subject' => $activity->subject,
                'description' => $activity->description,
                'due_date' => $this->toDateTimeString($activity->due_date),
                'completed_at' => $this->toDateTimeString($activity->completed_at),
                'user_name' => $activity->user?->name,
            ])
            ->all();
    }

    /**
     * @param mixed $value
     *
     * @return string|null
     */
    private function toDateTimeString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->toDateTimeString();
    }
}
