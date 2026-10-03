<?php

declare(strict_types=1);

namespace Core\Lead\Application\Services;

use Core\Lead\Application\Contracts\LeadServiceInterface;
use Core\Lead\Application\DTOs\Requests\LeadCreateDTO;
use Core\Lead\Domain\Contracts\LeadRepositoryContract;
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
        $this->queueManager->push('lead.inbound.process', $dto); 
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
}
