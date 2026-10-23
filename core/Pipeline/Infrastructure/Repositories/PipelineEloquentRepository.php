<?php

declare(strict_types=1);

namespace Core\Pipeline\Infrastructure\Repositories;

use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Pipeline;
use Core\Pipeline\Domain\Contracts\PipelineQueryContract;
use Core\Pipeline\Domain\Contracts\PipelineRepositoryContract;
use Core\Pipeline\Domain\DataObjects\KanbanBoardData;
use Core\Pipeline\Domain\DataObjects\KanbanDealData;
use Core\Pipeline\Domain\DataObjects\KanbanStageData;
use Core\Pipeline\Domain\DataObjects\PipelineDetailData;
use Core\Pipeline\Domain\Entities\PipelineEntity;
use Core\Pipeline\Domain\ValueObjects\PipelineStage;
use Core\Pipeline\Infrastructure\DataObjects\StoredPipelineData;
use Core\Pipeline\Infrastructure\Mappers\PipelineEntityMapper;
use Core\Shared\Infrastructure\Repositories\BaseRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Eloquent-backed persistence for pipelines and their stages. The only place in
 * the module where Laravel/Eloquent is used; results are mapped to Domain
 * Entities first.
 *
 * @implements PipelineRepositoryContract
 * @implements PipelineQueryContract
 */
final class PipelineEloquentRepository extends BaseRepository implements PipelineRepositoryContract, PipelineQueryContract
{
    /**
     * @param Pipeline            $model
     * @param PipelineEntityMapper $mapper
     */
    public function __construct(
        Pipeline $model,
        private readonly PipelineEntityMapper $mapper,
    ) {
        parent::__construct($model);
    }

    /**
     * Persists the pipeline and its stages, then reads the graph back. The
     * writes and the read run in one transaction, so the returned state is the
     * committed state.
     *
     * @param PipelineEntity $pipeline
     *
     * @return PipelineEntity The stored pipeline with its stages.
     *
     * @throws RuntimeException When the pipeline row could not be written.
     */
    public function save(PipelineEntity $pipeline): PipelineEntity
    {
        $storedPipeline = DB::transaction(function () use ($pipeline): StoredPipelineData {
            $pipelineModel = $this->createRecord($this->mapper->toPersistenceArray($pipeline));

            if (! $pipelineModel instanceof Pipeline) {
                throw new RuntimeException('The pipeline row could not be written.');
            }

            $this->writeStages($pipeline->orderedStages(), $pipelineModel->id);

            return $this->toStoredPipelineData($pipelineModel->refresh());
        });

        return $this->mapper->toEntity(
            data: $storedPipeline->pipeline,
            stages: $storedPipeline->stages,
        );
    }

    /**
     * Reads a pipeline together with its stages while holding a row lock, so a
     * concurrent write cannot slip between the read and the save.
     *
     * @param int $id
     *
     * @return PipelineEntity|null Null when no pipeline carries the id.
     */
    public function findByIdWithLock(int $id): ?PipelineEntity
    {
        $pipelineModel = $this->findRecordForUpdate($id);

        if (! $pipelineModel instanceof Pipeline) {
            return null;
        }

        $pipelineModel->load('stages');

        return $this->mapper->toEntity(
            data: $pipelineModel->toArray(),
            stages: $this->toStoredPipelineData($pipelineModel)->stages,
        );
    }

    /**
     * Persists changes to an existing pipeline and its stages, then reads the
     * graph back. The writes and the read run in one transaction, so the
     * returned state is the committed state.
     *
     * An edit replaces the stage set, so the stored stages are dropped and the
     * incoming ones written in their place. Both halves run in one transaction,
     * so a failure leaves the stored stages as they were.
     *
     * @param PipelineEntity $pipeline
     *
     * @return PipelineEntity The stored pipeline with its stages.
     *
     * @throws RuntimeException When the entity has no ID, or the pipeline row no longer exists.
     */
    public function update(PipelineEntity $pipeline): PipelineEntity
    {
        if ($pipeline->id === null) {
            throw new RuntimeException('Cannot update a pipeline that has no ID.');
        }

        $storedPipeline = DB::transaction(function () use ($pipeline): StoredPipelineData {
            $pipelineModel = $this->findRecord($pipeline->id);

            if (! $pipelineModel instanceof Pipeline) {
                throw new RuntimeException(sprintf(
                    'Pipeline with ID %d no longer exists.',
                    $pipeline->id,
                ));
            }

            $pipelineModel->fill($this->mapper->toPersistenceArray($pipeline));
            $pipelineModel->save();

            DealStage::query()->where('pipeline_id', $pipeline->id)->delete();

            $this->writeStages($pipeline->orderedStages(), $pipeline->id);

            return $this->toStoredPipelineData($pipelineModel->refresh());
        });

        return $this->mapper->toEntity(
            data: $storedPipeline->pipeline,
            stages: $storedPipeline->stages,
        );
    }

    /**
     * Returns one pipeline together with the stages it is made of.
     *
     * The stages are ordered here so the read is sequenced, and read as plain
     * column rows so nothing above Infrastructure has to know how Eloquent
     * nests a relation.
     *
     * @param int $id
     *
     * @return PipelineDetailData|null Null when no pipeline carries the id.
     */
    public function findDetailById(int $id): ?PipelineDetailData
    {
        $pipelineModel = $this->findRecordWithRelations($id, ['stages']);

        if (! $pipelineModel instanceof Pipeline) {
            return null;
        }

        return $this->toDetailData($pipelineModel);
    }

    /**
     * Returns every pipeline with the stages it is made of.
     *
     * A pipeline that has no pipeline is not a thing this read has to invent,
     * so an empty list is the ordinary answer when none exist.
     *
     * @return array<int, PipelineDetailData> Empty when no pipeline exists.
     */
    public function getAllWithStages(): array
    {
        return Pipeline::query()
            ->with(['stages' => fn($query) => $query->orderBy('order_position')])
            ->orderBy('id')
            ->get()
            ->map(fn(Pipeline $pipelineModel) => $this->toDetailData($pipelineModel))
            ->all();
    }

    /**
     * Returns one pipeline with its stages and the deals sitting in each, as the
     * Kanban board shows them.
     *
     * Stages come back in the order they are worked through and each column's
     * deals newest first. The related company and contact are narrowed to their
     * name columns at the query, so the board never loads a whole record it
     * only prints a name from.
     *
     * @param int $id
     *
     * @return KanbanBoardData|null Null when no pipeline carries the id.
     */
    public function findKanbanBoardById(int $id): ?KanbanBoardData
    {
        $pipelineModel = Pipeline::query()
            ->with([
                'stages' => fn($query) => $query
                    ->orderBy('order_position')
                    ->with([
                        'deals' => fn($dealQuery) => $dealQuery
                            ->with(['company:id,name', 'contact:id,name'])
                            ->latest(),
                    ]),
            ])
            ->find($id);

        if (! $pipelineModel instanceof Pipeline) {
            return null;
        }

        return new KanbanBoardData(
            pipeline: $this->mapper->toEntity(data: $pipelineModel->toArray()),
            stages: $pipelineModel->stages
                ->map(fn(DealStage $stage) => $this->toKanbanStageData($stage))
                ->all(),
        );
    }

    /**
     * Flattens one stage and the deals sitting in it. The deals are read from
     * the loaded relation as plain column values.
     *
     * @param DealStage $stageModel
     *
     * @return KanbanStageData
     */
    private function toKanbanStageData(DealStage $stageModel): KanbanStageData
    {
        return new KanbanStageData(
            id: $stageModel->id,
            name: $stageModel->name,
            orderPosition: $stageModel->order_position,
            probability: $stageModel->probability,
            deals: $stageModel->deals
                ->map(fn(Deal $deal) => $this->toKanbanDealData($deal))
                ->all(),
        );
    }

    /**
     * Flattens one deal. The amount is a decimal string as stored, so the
     * column's two decimals survive the trip to the client.
     *
     * @param Deal $dealModel
     *
     * @return KanbanDealData
     */
    private function toKanbanDealData(Deal $dealModel): KanbanDealData
    {
        return new KanbanDealData(
            id: $dealModel->id,
            title: $dealModel->title,
            companyId: $dealModel->company_id,
            contactId: $dealModel->contact_id,
            amount: (string) $dealModel->amount,
            currency: $dealModel->currency,
            pipelineId: $dealModel->pipeline_id,
            dealStageId: $dealModel->deal_stage_id,
            expectedCloseDate: $this->toDateString($dealModel->expected_close_date),
            actualCloseDate: $this->toDateString($dealModel->actual_close_date),
            createdAt: $this->toDateTimeString($dealModel->created_at),
            updatedAt: $this->toDateTimeString($dealModel->updated_at),
            companyName: $dealModel->company?->name,
            contactName: $dealModel->contact?->name,
        );
    }

    /**
     * The casts already turn these into date objects, so only the string form is
     * left to take.
     *
     * @param mixed $value
     *
     * @return string|null
     */
    private function toDateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value->toDateString();
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

    /**
     * Writes one deal_stages row per stage. The stage ids are the database's to
     * assign, so they are not part of the insert.
     *
     * @param array<int, PipelineStage> $stages
     * @param int                       $pipelineId
     *
     * @return void
     *
     * @throws RuntimeException When a stage row could not be written.
     */
    private function writeStages(array $stages, int $pipelineId): void
    {
        foreach ($stages as $stage) {
            $isWritten = DealStage::query()->insert(
                $this->mapper->toStagePersistenceArray($stage, $pipelineId),
            );

            if (! $isWritten) {
                throw new RuntimeException(sprintf(
                    'The stage [%s] could not be written for pipeline %d.',
                    $stage->name,
                    $pipelineId,
                ));
            }
        }
    }

    /**
     * Flattens one pipeline model and its stages into the read model both
     * detail reads return. The stages must already be loaded on the model.
     *
     * @param Pipeline $pipelineModel
     *
     * @return PipelineDetailData
     */
    private function toDetailData(Pipeline $pipelineModel): PipelineDetailData
    {
        $storedPipeline = $this->toStoredPipelineData($pipelineModel);

        return new PipelineDetailData(
            pipeline: $this->mapper->toEntity(
                data: $storedPipeline->pipeline,
                stages: $storedPipeline->stages,
            ),
            stages: $storedPipeline->stages,
        );
    }

    /**
     * Reads the pipeline row again with its stages, so the returned graph is the
     * committed state rather than the state the insert was handed.
     *
     * @param Pipeline $pipelineModel
     *
     * @return StoredPipelineData
     */
    private function toStoredPipelineData(Pipeline $pipelineModel): StoredPipelineData
    {
        return new StoredPipelineData(
            pipeline: $pipelineModel->toArray(),
            stages: $pipelineModel->stages()
                ->orderBy('order_position')
                ->get()
                ->map(fn(DealStage $stage) => $stage->toArray())
                ->all(),
        );
    }

}
