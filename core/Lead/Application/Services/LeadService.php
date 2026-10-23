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
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Illuminate\Database\QueryException;

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

    private const ERROR_ALREADY_EXISTS = 'LEAD_ALREADY_EXISTS';

    private const ERROR_CREATE_FAILED = 'LEAD_CREATE_FAILED';

    /**
     * SQLSTATE for an integrity constraint violation - the unique email or
     * phone index rejecting a duplicate.
     */
    private const SQLSTATE_INTEGRITY_VIOLATION = '23000';

    public function __construct(
        private readonly LeadRepositoryContract $leadRepository,
        private readonly LeadQueryContract $leadQuery,
        private readonly LeadResponseMapper $leadResponseMapper,
        private readonly QueueManagerContract $queueManager,
        private readonly TransactionManagerContract $transactionManager,
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
     * Creates a lead inside a transaction.
     *
     * The lookup below rejects the common duplicate, but two writers can both
     * pass it before either inserts. The unique email/phone index is what
     * actually decides, so the insert's integrity violation is caught and
     * returned as the same domain failure a caller would get from the lookup.
     * Queued inbound leads rely on that: a duplicate has to come back as a
     * rejection rather than an exception that lands the job in failed_jobs.
     *
     * @param LeadCreateDTO $leadCreateDTO
     *
     * @return Result Contains the LeadResponseDTO on success.
     */
    public function createLead(LeadCreateDTO $leadCreateDTO): Result
    {
        try {
            return $this->transactionManager->run(function () use ($leadCreateDTO): Result {
                $existingLead = $this->leadRepository->findByEmail($leadCreateDTO->email)
                            ?? $this->leadRepository->findByPhone($leadCreateDTO->phone);

                if ($existingLead) {
                    return $this->alreadyExists();
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

                // The repository hands back the persisted row, so this response
                // carries stored timestamps rather than the ones the input implied.
                return Result::success(
                    data: $this->leadResponseMapper->toResponseDTOFromEntity($leadEntity),
                    message: 'Lead created successfully.'
                );
            });
        } catch (QueryException $exception) {
            // A race the lookup could not see lost to the unique index. Any
            // other constraint failure is a real write error and must surface.
            if ($this->isDuplicateKey($exception)) {
                return $this->alreadyExists();
            }

            return Result::failure(
                message: 'Failed to create the lead. Please try again.',
                errorCode: self::ERROR_CREATE_FAILED,
            );
        } catch (\Throwable) {
            return Result::failure(
                message: 'Failed to create the lead. Please try again.',
                errorCode: self::ERROR_CREATE_FAILED,
            );
        }
    }

    /**
     * Reports a lead that the system already holds.
     *
     * @return Result
     */
    private function alreadyExists(): Result
    {
        return Result::failure(
            message: 'This lead already exists in our system.',
            errorCode: self::ERROR_ALREADY_EXISTS,
        );
    }

    /**
     * Tells a duplicate-key rejection apart from any other database error.
     *
     * @param QueryException $exception
     *
     * @return bool
     */
    private function isDuplicateKey(QueryException $exception): bool
    {
        return (string) $exception->getCode() === self::SQLSTATE_INTEGRITY_VIOLATION;
    }

    /**
     * Moves a lead to a new status, letting the domain entity decide whether
     * the change is allowed.
     *
     * @param int        $leadId
     * @param LeadStatus $status
     *
     * @return Result Contains the LeadResponseDTO on success.
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

        // The repository hands back the stored row, so the response carries the
        // timestamp this write produced rather than the one we loaded.
        $savedLead = $this->leadRepository->saveStatus($lead);

        return Result::success(
            data: $this->leadResponseMapper->toResponseDTOFromEntity($savedLead),
            message: 'Lead status updated successfully.'
        );
    }
}
