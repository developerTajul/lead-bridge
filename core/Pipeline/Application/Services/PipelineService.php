<?php

declare(strict_types=1);

namespace Core\Pipeline\Application\Services;

use Core\Pipeline\Application\DTOs\Requests\StorePipelineDTO;
use Core\Pipeline\Application\DTOs\Requests\StorePipelineStageDTO;
use Core\Pipeline\Application\DTOs\Requests\UpdatePipelineDTO;
use Core\Pipeline\Application\Mappers\PipelineResponseMapper;
use Core\Pipeline\Domain\Contracts\PipelineRepositoryContract;
use Core\Pipeline\Domain\Entities\PipelineEntity;
use Core\Pipeline\Domain\Exceptions\InvalidPipelineException;
use Core\Pipeline\Domain\ValueObjects\PipelineStage;
use Core\Shared\Application\DTOs\Result;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Psr\Log\LoggerInterface;

/**
 * Handles pipeline write use cases. Business rules live in PipelineEntity.
 */
final class PipelineService
{
    private const ERROR_INVALID = 'PIPELINE_INVALID';

    private const ERROR_CREATE_FAILED = 'PIPELINE_CREATE_FAILED';

    private const ERROR_NOT_FOUND = 'PIPELINE_NOT_FOUND';

    private const ERROR_UPDATE_FAILED = 'PIPELINE_UPDATE_FAILED';

    /**
     * @param PipelineRepositoryContract $pipelineRepository
     * @param PipelineResponseMapper     $responseMapper
     * @param TransactionManagerContract $transactionManager
     * @param LoggerInterface            $logger
     */
    public function __construct(
        private readonly PipelineRepositoryContract $pipelineRepository,
        private readonly PipelineResponseMapper $responseMapper,
        private readonly TransactionManagerContract $transactionManager,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Creates a pipeline together with the stages it is made of.
     *
     * The entity guards the pipeline's own invariants and each stage value
     * object guards its own values; the DTOs only carry input.
     *
     * @param StorePipelineDTO $storePipelineDTO
     *
     * @return Result Contains the PipelineResponseDTO on success.
     */
    public function createPipeline(StorePipelineDTO $storePipelineDTO): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            $pipelineEntity = PipelineEntity::create(
                name: $storePipelineDTO->name,
                isDefault: $storePipelineDTO->isDefault,
                stages: array_map($this->toStage(...), $storePipelineDTO->stages),
            );

            $savedPipeline = $this->pipelineRepository->save($pipelineEntity);

            $pipelineResponseDTO = $this->responseMapper->toResponseDTO($savedPipeline);

            $this->transactionManager->commit();
        } catch (InvalidPipelineException $exception) {
            $this->transactionManager->rollback();

            return Result::failure(
                message: $exception->getMessage(),
                errorCode: self::ERROR_INVALID,
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Pipeline creation failed.', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to create the pipeline. Please try again.',
                errorCode: self::ERROR_CREATE_FAILED,
            );
        }

        return Result::success(
            data: $pipelineResponseDTO,
            message: 'Pipeline created successfully.',
        );
    }

    /**
     * Applies an edit to an existing pipeline and its stages.
     *
     * The pipeline is read under a write lock so the state the edit is applied
     * to is the state being replaced, and the whole edit is one transaction: a
     * rejected stage set leaves the stored pipeline as it was.
     *
     * @param UpdatePipelineDTO $updatePipelineDTO
     *
     * @return Result Contains the PipelineResponseDTO on success.
     */
    public function update(UpdatePipelineDTO $updatePipelineDTO): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            $pipelineEntity = $this->pipelineRepository->findByIdWithLock($updatePipelineDTO->id);

            if ($pipelineEntity === null) {
                $this->transactionManager->rollback();

                return Result::failure(
                    message: 'Pipeline not found.',
                    errorCode: self::ERROR_NOT_FOUND,
                );
            }

            $updatedPipeline = $pipelineEntity->applyChanges(
                name: $updatePipelineDTO->name,
                isDefault: $updatePipelineDTO->isDefault,
                stages: array_map($this->toStage(...), $updatePipelineDTO->stages),
            );

            $savedPipeline = $this->pipelineRepository->update($updatedPipeline);

            $pipelineResponseDTO = $this->responseMapper->toResponseDTO($savedPipeline);

            $this->transactionManager->commit();
        } catch (InvalidPipelineException $exception) {
            $this->transactionManager->rollback();

            return Result::failure(
                message: $exception->getMessage(),
                errorCode: self::ERROR_INVALID,
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('Pipeline update failed.', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to update the pipeline. Please try again.',
                errorCode: self::ERROR_UPDATE_FAILED,
            );
        }

        return Result::success(
            data: $pipelineResponseDTO,
            message: 'Pipeline updated successfully.',
        );
    }

    /**
     * Builds the stage the payload describes. The values reach the guard through
     * the value object, so a rejected stage never becomes part of a pipeline.
     *
     * @param StorePipelineStageDTO $storePipelineStageDTO
     *
     * @return PipelineStage
     *
     * @throws InvalidPipelineException When a stage value would break an invariant.
     */
    private function toStage(StorePipelineStageDTO $storePipelineStageDTO): PipelineStage
    {
        return PipelineStage::create(
            name: $storePipelineStageDTO->name,
            orderPosition: $storePipelineStageDTO->orderPosition,
            probability: $storePipelineStageDTO->probability,
        );
    }
}
