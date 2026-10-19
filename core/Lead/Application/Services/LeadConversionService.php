<?php

declare(strict_types=1);

namespace Core\Lead\Application\Services;

use Core\Lead\Application\DTOs\Responses\LeadConversionResponseDTO;
use Core\Lead\Domain\Contracts\LeadRepositoryContract;
use Core\Lead\Domain\Entities\LeadEntity;
use Core\Lead\Domain\Events\LeadConverted;
use Core\Lead\Domain\Exceptions\InvalidLeadException;
use Core\Shared\Application\DTOs\Result;
use Core\Shared\Domain\Contracts\EventDispatcherContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

/**
 * Handles converting a qualified lead.
 */
final class LeadConversionService
{
    private const ERROR_INVALID = 'LEAD_INVALID';

    private const ERROR_NOT_FOUND = 'LEAD_NOT_FOUND';

    private const ERROR_CONVERT_FAILED = 'LEAD_CONVERT_FAILED';

    public function __construct(
        private readonly LeadRepositoryContract $leadRepository,
        private readonly TransactionManagerContract $transactionManager,
        private readonly EventDispatcherContract $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Moves the lead to converted and announces it to the rest of the system.
     *
     * The lead's own rules decide whether it may convert, so a lead that is not
     * qualified is rejected before anything is written.
     *
     * @param int $leadId
     *
     * @return Result Contains the LeadConversionResponseDTO on success.
     */
    public function convert(int $leadId): Result
    {
        $lead = $this->leadRepository->findEntityById($leadId);

        if ($lead === null) {
            return Result::failure('Lead not found.', self::ERROR_NOT_FOUND);
        }

        try {
            $this->transactionManager->beginTransaction();

            // The entity returns a converted copy; the original is kept so a
            // failed write leaves nothing half-changed.
            $convertedLead = $lead->converted();

            $this->leadRepository->saveStatus($convertedLead);

            $this->transactionManager->commit();
        } catch (InvalidLeadException $exception) {
            $this->transactionManager->rollback();

            return Result::failure($exception->getMessage(), self::ERROR_INVALID);
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Lead conversion failed.', [
                'lead_id' => $leadId,
                'message' => $exception->getMessage(),
            ]);

            return Result::failure(
                message: 'Failed to convert the lead. Please try again.',
                errorCode: self::ERROR_CONVERT_FAILED,
            );
        }

        // The entity in hand was loaded before the write, so its timestamps are
        // the ones the row carried beforehand. The moment of conversion is now.
        $convertedAt = new DateTimeImmutable();

        // Past this point the write has committed: a failing listener must not
        // turn a converted lead into a reported failure.
        $this->dispatchConvertedEvent($convertedLead, $convertedAt);

        return Result::success(
            data: $this->toResponseDTO($convertedLead, $convertedAt),
            message: 'Lead converted successfully.',
        );
    }

    /**
     * Announces the conversion. Listeners react to the stored lead, and one
     * that fails is logged rather than surfaced to the caller.
     *
     * @param LeadEntity        $lead
     * @param DateTimeImmutable $convertedAt
     *
     * @return void
     */
    private function dispatchConvertedEvent(LeadEntity $lead, DateTimeImmutable $convertedAt): void
    {
        $event = new LeadConverted(
            leadId: $lead->id,
            name: $lead->name,
            email: $lead->email,
            phone: $lead->phone,
            companyName: $lead->companyName,
            source: $lead->source,
            occurredAt: $convertedAt,
        );

        try {
            $this->eventDispatcher->dispatch($event);
        } catch (\Throwable $exception) {
            $this->logger->error('Lead converted event listener failed.', [
                'lead_id' => $lead->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Builds the response from the converted lead.
     *
     * @param LeadEntity        $lead
     * @param DateTimeImmutable $convertedAt
     *
     * @return LeadConversionResponseDTO
     */
    private function toResponseDTO(LeadEntity $lead, DateTimeImmutable $convertedAt): LeadConversionResponseDTO
    {
        return new LeadConversionResponseDTO(
            id: $lead->id,
            name: $lead->name,
            email: $lead->email,
            phone: $lead->phone,
            company_name: $lead->companyName,
            status: $lead->getStatus()->value,
            status_label: $lead->getStatus()->label(),
            source: $lead->source,
            converted_at: $convertedAt->format('Y-m-d H:i:s'),
        );
    }
}
