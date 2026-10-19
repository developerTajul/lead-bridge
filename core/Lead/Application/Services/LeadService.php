<?php

declare(strict_types=1);

namespace Core\Lead\Application\Services;

use Core\Lead\Application\Contracts\LeadQueryContract;
use Core\Lead\Application\Contracts\LeadServiceInterface;
use Core\Lead\Application\DTOs\Requests\LeadCreateDTO;
use Core\Lead\Application\DTOs\Requests\LeadFilterDTO;
use Core\Lead\Application\DTOs\Responses\LeadProjectionDTO;
use Core\Lead\Application\Mappers\LeadResponseMapper;
use Core\Lead\Domain\Contracts\LeadRepositoryContract;
use Core\Lead\Domain\Enums\LeadStatus;
use Core\Lead\Domain\Exceptions\InvalidLeadException;
use Core\Lead\Domain\ValueObjects\LeadFilter;
use Core\Shared\Application\Contracts\QueueManagerContract;
use Core\Lead\Domain\DataObjects\LeadData;
use Core\Shared\Application\DTOs\Result;

/**
 * Handles lead use cases.
 *
 * @implements \Core\Lead\Application\Contracts\LeadServiceInterface
 */
final class LeadService implements LeadServiceInterface
{
    private const ERROR_INVALID = 'LEAD_INVALID';

    private const ERROR_INDEX_FAILED = 'LEAD_INDEX_FAILED';

    private const ERROR_NOT_FOUND = 'LEAD_NOT_FOUND';

    private const ERROR_FETCH_FAILED = 'LEAD_FETCH_FAILED';

    public function __construct(
        private readonly LeadRepositoryContract $leadRepository,
        private readonly LeadQueryContract $leadQuery,
        private readonly LeadResponseMapper $leadResponseMapper,
        private readonly QueueManagerContract $queueManager
    ) {}

    /**
     * Captures an inbound lead by dispatching it to the background queue.
     *
     * @param LeadCreateDTO $dto
     */
    public function captureLead(LeadCreateDTO $dto): void
    {
        $this->queueManager->push('lead.inbound.process', $dto);
    }

    /**
     * Returns the paginated lead list matching the given criteria.
     *
     * @param LeadFilterDTO $leadFilterDTO
     *
     * @return Result Contains the PaginatedResult of LeadResponseDTO on success.
     */
    public function getLeads(LeadFilterDTO $leadFilterDTO): Result
    {
        try {
            $leadFilter = new LeadFilter(
                status: $leadFilterDTO->status,
                source: $leadFilterDTO->source,
                search: $leadFilterDTO->search,
                perPage: $leadFilterDTO->perPage,
            );

            $paginatedLeads = $this->leadQuery->getFilteredLeads($leadFilter);

            // Entities become response DTOs here, so nothing downstream of the
            // Service ever sees a domain object.
            $mappedPaginatedResult = $paginatedLeads->map(
                fn(LeadProjectionDTO $projection) => $this->leadResponseMapper->toResponseDTO($projection)
            );

            return Result::success(
                data: $mappedPaginatedResult,
                message: 'Leads retrieved successfully.'
            );
        } catch (InvalidLeadException $exception) {
            // A rejected filter is bad input, not a system failure.
            return Result::failure($exception->getMessage(), self::ERROR_INVALID);
        } catch (\Throwable) {
            return Result::failure('Failed to retrieve leads.', self::ERROR_INDEX_FAILED);
        }
    }

    /**
     * Returns a single lead by its ID.
     *
     * @param int $id
     *
     * @return Result Contains the LeadResponseDTO on success.
     */
    public function getLeadById(int $id): Result
    {
        try {
            $leadProjection = $this->leadQuery->findById($id);

            if (! $leadProjection) {
                return Result::failure('Lead not found.', self::ERROR_NOT_FOUND);
            }

            return Result::success(
                data: $this->leadResponseMapper->toResponseDTO($leadProjection),
                message: 'Lead retrieved successfully.'
            );
        } catch (\Throwable) {
            return Result::failure('Failed to retrieve lead.', self::ERROR_FETCH_FAILED);
        }
    }

    /**
     * Creates a lead directly via the repository.
     *
     * @param LeadCreateDTO $leadCreateDTO
     *
     * @return Result Contains the LeadEntity on success.
     */
    public function createLead(LeadCreateDTO $leadCreateDTO): Result
    {
        try {
            $existingLead = $this->leadRepository->findByEmail($leadCreateDTO->email)
                        ?? $this->leadRepository->findByPhone($leadCreateDTO->phone);

            if ($existingLead) {
                return Result::failure(
                    message: 'This lead already exists in our system.',
                    errorCode: 'LEAD_ALREADY_EXISTS'
                );
            }

            $leadData = new LeadData(
                name: $leadCreateDTO->name,
                email: $leadCreateDTO->email,
                phone: $leadCreateDTO->phone,
                companyName: $leadCreateDTO->companyName,
                status: $leadCreateDTO->status,
                source: $leadCreateDTO->source,
            );

            $leadEntity = $this->leadRepository->create($leadData);

            return Result::success(data: $leadEntity, message: 'Lead created successfully.');
        } catch (\Throwable) {
            return Result::failure(
                message: 'Failed to create the lead. Please try again.',
                errorCode: 'LEAD_CREATE_FAILED',
            );
        }
    }

    /**
     * Moves a lead to a new status, letting the domain entity decide whether
     * the change is allowed.
     *
     * @param int        $leadId
     * @param LeadStatus $status
     *
     * @return Result Contains the LeadEntity on success.
     */
    public function changeStatus(int $leadId, LeadStatus $status): Result
    {
        $lead = $this->leadRepository->findEntityById($leadId);

        if ($lead === null) {
            return Result::failure('Lead not found.', self::ERROR_NOT_FOUND);
        }

        try {
            $lead->transitionTo($status);
        } catch (InvalidLeadException $e) {
            return Result::failure($e->getMessage(), self::ERROR_INVALID);
        }

        $this->leadRepository->saveStatus($lead);

        return Result::success(data: $lead, message: 'Lead status updated successfully.');
    }
}
