<?php

declare(strict_types=1);

namespace Core\Lead\Infrastructure\Repositories;

use App\Models\Lead;
use Core\Lead\Domain\Contracts\LeadRepositoryContract;
use Core\Lead\Domain\DataObjects\LeadData;
use Core\Lead\Domain\Entities\LeadEntity;
use Core\Lead\Infrastructure\Mappers\LeadEntityMapper;
use Core\Shared\Infrastructure\Repositories\BaseRepository;

/**
 * Eloquent-backed persistence for leads.
 *
 * @implements \Core\Lead\Domain\Contracts\LeadRepositoryContract
 */
final class LeadRepository extends BaseRepository implements LeadRepositoryContract
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

        return $this->mapper->toEntity($leadModel->toArray());
    }


    public function findByEmail(string $email): ?LeadEntity
    {
        $leadModel = $this->model->where('email', $email)->first();

        return $leadModel ? $this->mapper->toEntity($leadModel->toArray()) : null;
    }

    public function findByPhone(string $phone): ?LeadEntity
    {
        $leadModel = $this->model->where('phone', $phone)->first();

        return $leadModel ? $this->mapper->toEntity($leadModel->toArray()) : null;
    }
}
