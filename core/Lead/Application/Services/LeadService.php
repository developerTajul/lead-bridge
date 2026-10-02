<?php

declare(strict_types=1);

namespace Core\Lead\Application\Services;

use Core\Lead\Application\DTOs\Requests\LeadCreateDTO;
use Core\Lead\Domain\Contracts\LeadRepositoryContract;
use Core\Lead\Application\Contracts\QueueManagerContract;
use Core\Lead\Domain\DataObjects\LeadData;
use Core\Shared\Application\DTOs\Result;
use App\Jobs\ProcessInboundLead;

/**
 * Handles lead use cases.
 */
final class LeadService 
{
    public function __construct(
        private readonly LeadRepositoryContract $leadRepository,
        private readonly QueueManagerContract $queueManager
    ) {}

    /**
     * Captures an inbound lead by dispatching it to the background queue.
     *
     * @param LeadCreateDTO $dto
     */
    public function captureLead(LeadCreateDTO $dto): void
    {
        // ProcessInboundLead::dispatch($dto);
        $this->queueManager->push(ProcessInboundLead::class, $dto); 
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
        } catch (\Throwable $exception) {
            return Result::failure(
                message: 'Failed to create the lead. Please try again.',
                errorCode: 'LEAD_CREATE_FAILED',
            );
        }
    }
}
