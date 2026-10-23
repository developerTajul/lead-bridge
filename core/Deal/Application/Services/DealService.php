<?php

declare(strict_types=1);

namespace Core\Deal\Application\Services;

use Core\Deal\Application\DTOs\Requests\StoreDealDTO;
use Core\Deal\Application\DTOs\Requests\UpdateDealDTO;
use Core\Deal\Application\Mappers\DealResponseMapper;
use Core\Deal\Domain\Contracts\DealRepositoryContract;
use Core\Deal\Domain\Entities\DealEntity;
use Core\Deal\Domain\Exceptions\InvalidDealException;
use Core\Shared\Application\DTOs\Result;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

/**
 * Handles deal write use cases. Business rules live in DealEntity.
 */
final class DealService
{
    private const ERROR_INVALID = 'DEAL_INVALID';

    private const ERROR_CREATE_FAILED = 'DEAL_CREATE_FAILED';

    private const ERROR_NOT_FOUND = 'DEAL_NOT_FOUND';

    private const ERROR_UPDATE_FAILED = 'DEAL_UPDATE_FAILED';

    private const ERROR_DELETE_FAILED = 'DEAL_DELETE_FAILED';

    /**
     * The form the close date column holds.
     */
    private const DATE_FORMAT = 'Y-m-d';

    /**
     * @param DealRepositoryContract     $dealRepository
     * @param DealResponseMapper         $responseMapper
     * @param TransactionManagerContract $transactionManager
     * @param LoggerInterface            $logger
     */
    public function __construct(
        private readonly DealRepositoryContract $dealRepository,
        private readonly DealResponseMapper $responseMapper,
        private readonly TransactionManagerContract $transactionManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Creates a deal inside a transaction.
     *
     * The entity guards its own invariants; the DTO only carries input.
     *
     * @param StoreDealDTO $storeDealDTO
     *
     * @return Result Contains the DealResponseDTO on success.
     */
    public function createDeal(StoreDealDTO $storeDealDTO): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            $dealEntity = DealEntity::create(
                title: $storeDealDTO->title,
                pipelineId: $storeDealDTO->pipelineId,
                dealStageId: $storeDealDTO->dealStageId,
                companyId: $storeDealDTO->companyId,
                contactId: $storeDealDTO->contactId,
                amount: $storeDealDTO->amount,
                currency: $storeDealDTO->currency,
                expectedCloseDate: $storeDealDTO->expectedCloseDate,
                actualCloseDate: $storeDealDTO->actualCloseDate,
            );

            $savedDeal = $this->dealRepository->save($dealEntity);

            $dealResponseDTO = $this->responseMapper->toResponseDTO($savedDeal);

            $this->transactionManager->commit();
        } catch (InvalidDealException $exception) {
            $this->transactionManager->rollback();

            return Result::failure(
                message: $exception->getMessage(),
                errorCode: self::ERROR_INVALID,
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Deal creation failed.', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to create the deal. Please try again.',
                errorCode: self::ERROR_CREATE_FAILED,
            );
        }

        return Result::success(
            data: $dealResponseDTO,
            message: 'Deal created successfully.',
        );
    }

    /**
     * Updates a deal inside a transaction, holding a row lock from the read
     * through to the save.
     *
     * @param UpdateDealDTO $updateDealDTO
     *
     * @return Result Contains the DealResponseDTO on success.
     */
    public function updateDeal(UpdateDealDTO $updateDealDTO): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            $dealEntity = $this->dealRepository->findByIdWithLock($updateDealDTO->id);

            if ($dealEntity === null) {
                $this->transactionManager->rollback();

                return Result::failure(
                    message: 'Deal not found.',
                    errorCode: self::ERROR_NOT_FOUND,
                );
            }

            // Tell the entity to update itself; it guards its own invariants and
            // hands back the updated instance. The close date follows the stage
            // the deal lands in, which is the entity's rule to apply.
            $updatedDeal = $dealEntity->applyChanges(
                title: $updateDealDTO->title,
                companyId: $updateDealDTO->companyId,
                unlinkCompany: $updateDealDTO->unlinkCompany,
                contactId: $updateDealDTO->contactId,
                unlinkContact: $updateDealDTO->unlinkContact,
                amount: $updateDealDTO->amount,
                currency: $updateDealDTO->currency,
                pipelineId: $updateDealDTO->pipelineId,
                dealStageId: $updateDealDTO->dealStageId,
                stageName: $updateDealDTO->stageName,
                expectedCloseDate: $updateDealDTO->expectedCloseDate,
                closeDate: $this->today(),
            );

            $savedDeal = $this->dealRepository->update($updatedDeal);

            $dealResponseDTO = $this->responseMapper->toResponseDTO($savedDeal);

            $this->transactionManager->commit();
        } catch (InvalidDealException $exception) {
            $this->transactionManager->rollback();

            return Result::failure(
                message: $exception->getMessage(),
                errorCode: self::ERROR_INVALID,
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Deal update failed.', [
                'id' => $updateDealDTO->id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to update the deal. Please try again.',
                errorCode: self::ERROR_UPDATE_FAILED,
            );
        }

        return Result::success(
            data: $dealResponseDTO,
            message: 'Deal updated successfully.',
        );
    }

    /**
     * Deletes a deal inside a transaction, holding a row lock from the read
     * through to the delete.
     *
     * @param int $id
     *
     * @return Result
     */
    public function deleteDeal(int $id): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            $dealEntity = $this->dealRepository->findByIdWithLock($id);

            if ($dealEntity === null) {
                $this->transactionManager->rollback();

                return Result::failure(
                    message: 'Deal not found.',
                    errorCode: self::ERROR_NOT_FOUND,
                );
            }

            $this->dealRepository->delete($dealEntity);

            $this->transactionManager->commit();
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Deal deletion failed.', [
                'id' => $id,
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to delete the deal. Please try again.',
                errorCode: self::ERROR_DELETE_FAILED,
            );
        }

        return Result::success(
            data: null,
            message: 'Deal deleted successfully.',
        );
    }

    /**
     * Today's date, as the wall-clock day the close date column holds. The clock
     * is read here rather than inside the entity, so the rule itself stays a
     * pure function of the values it is handed, the way
     * LeadConversionService reads the conversion moment.
     *
     * @return string
     */
    private function today(): string
    {
        return (new DateTimeImmutable())->format(self::DATE_FORMAT);
    }
}
