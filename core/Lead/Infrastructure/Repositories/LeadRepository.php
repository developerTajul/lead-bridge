<?php

declare(strict_types=1);

namespace Core\Lead\Infrastructure\Repositories;

use App\Models\Lead;
use Core\Lead\Application\Contracts\LeadQueryContract;
use Core\Lead\Application\DTOs\Responses\LeadProjectionDTO;
use Core\Lead\Domain\Contracts\LeadRepositoryContract;
use Core\Lead\Domain\DataObjects\LeadData;
use Core\Lead\Domain\Entities\LeadEntity;
use Core\Lead\Domain\Enums\LeadStatus;
use Core\Lead\Domain\ValueObjects\LeadFilter;
use Core\Lead\Infrastructure\Mappers\LeadEntityMapper;
use Core\Shared\Domain\DataObjects\PaginatedResult;
use Core\Shared\Infrastructure\Repositories\BaseRepository;
use RuntimeException;

/**
 * Eloquent-backed persistence for leads.
 *
 * @implements \Core\Lead\Domain\Contracts\LeadRepositoryContract
 * @implements \Core\Lead\Application\Contracts\LeadQueryContract
 */
final class LeadRepository extends BaseRepository implements LeadRepositoryContract, LeadQueryContract
{
    /**
     * @param Lead             $model
     * @param LeadEntityMapper $mapper
     */
    public function __construct(
        Lead $model,
        private readonly LeadEntityMapper $mapper,
    ) {
        parent::__construct($model);
    }

    /**
     * @param LeadData $leadData
     *
     * @return LeadEntity
     */
    public function create(LeadData $leadData): LeadEntity
    {
        $leadModel = $this->createRecord($leadData->toArray());

        return $this->mapper->toEntity($leadModel->getAttributes());
    }


    public function findByEmail(string $email): ?LeadEntity
    {
        $leadModel = $this->model->where('email', $email)->first();

        return $leadModel ? $this->mapper->toEntity($leadModel->getAttributes()) : null;
    }

    public function findByPhone(string $phone): ?LeadEntity
    {
        $leadModel = $this->model->where('phone', $phone)->first();

        return $leadModel ? $this->mapper->toEntity($leadModel->getAttributes()) : null;
    }

    /**
     * @param int $id
     *
     * @return LeadEntity|null
     */
    public function findEntityById(int $id): ?LeadEntity
    {
        $leadModel = $this->findRecord($id);

        return $leadModel ? $this->mapper->toEntity($leadModel->getAttributes()) : null;
    }

    /**
     * Persists the lead's current status and returns the stored row, so the
     * caller maps post-write values instead of the ones it loaded.
     *
     * @param LeadEntity $lead
     *
     * @return LeadEntity
     */
    public function saveStatus(LeadEntity $lead): LeadEntity
    {
        $leadModel = $this->updateRecord($lead->id, ['status' => $lead->getStatus()->value]);

        // The row is gone between the load and the write; upstream already
        // matched it, so failing loudly beats reporting a save that never happened.
        if ($leadModel === null) {
            throw new RuntimeException(sprintf('Lead %d disappeared during the status save.', $lead->id));
        }

        return $this->mapper->toEntity($leadModel->getAttributes());
    }

    /**
     * @param LeadFilter $filter
     *
     * @return PaginatedResult Items are LeadProjectionDTO instances.
     */
    public function getFilteredLeads(LeadFilter $filter): PaginatedResult
    {
        $query = Lead::query();

        if ($filter->status !== null) {
            $query->where('status', $filter->status->value);
        }

        if ($filter->source !== null) {
            $query->where('source', $filter->source);
        }

        if ($filter->search !== null) {
            // One term matched against the identifying columns. Wrapped in a
            // closure so the ORs cannot escape a conjunction added later.
            $term = '%' . $filter->search . '%';

            $query->where(function ($query) use ($term) {
                $query->where('name', 'like', $term)
                      ->orWhere('email', 'like', $term)
                      ->orWhere('phone', 'like', $term)
                      ->orWhere('company_name', 'like', $term);
            });
        }

        // The id tie-breaks rows sharing a created_at. Without it the order of
        // equal keys is undefined, so a paginated offset can repeat a row on
        // one page and skip another.
        $eloquentPaginator = $query
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($filter->perPage);

        $projections = $eloquentPaginator
            ->getCollection()
            ->map(fn(Lead $leadModel) => $this->mapper->toProjection($leadModel->getAttributes()))
            ->all();

        return new PaginatedResult(
            items: $projections,
            total: $eloquentPaginator->total(),
            currentPage: $eloquentPaginator->currentPage(),
            perPage: $eloquentPaginator->perPage(),
            lastPage: $eloquentPaginator->lastPage(),
        );
    }

    /**
     * @param int $id
     *
     * @return LeadProjectionDTO|null
     */
    public function findById(int $id): ?LeadProjectionDTO
    {
        $leadModel = Lead::find($id);

        if (! $leadModel) {
            return null;
        }

        return $this->mapper->toProjection($leadModel->getAttributes());
    }
}
