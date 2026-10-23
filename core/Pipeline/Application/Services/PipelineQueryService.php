<?php

declare(strict_types=1);

namespace Core\Pipeline\Application\Services;

use Core\Pipeline\Application\Mappers\KanbanBoardResponseMapper;
use Core\Pipeline\Application\Mappers\PipelineResponseMapper;
use Core\Pipeline\Domain\Contracts\PipelineQueryContract;
use Core\Pipeline\Domain\DataObjects\PipelineDetailData;
use Core\Shared\Application\DTOs\Result;
use Psr\Log\LoggerInterface;

/**
 * Handles pipeline read use cases. Never changes state.
 */
final class PipelineQueryService
{
    private const ERROR_INDEX_FAILED = 'PIPELINE_INDEX_FAILED';

    private const ERROR_NOT_FOUND = 'PIPELINE_NOT_FOUND';

    private const ERROR_DETAIL_FAILED = 'PIPELINE_DETAIL_FAILED';

    /**
     * @param PipelineQueryContract    $pipelineQuery
     * @param PipelineResponseMapper   $responseMapper
     * @param KanbanBoardResponseMapper $kanbanResponseMapper
     * @param LoggerInterface          $logger
     */
    public function __construct(
        private readonly PipelineQueryContract $pipelineQuery,
        private readonly PipelineResponseMapper $responseMapper,
        private readonly KanbanBoardResponseMapper $kanbanResponseMapper,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Returns every pipeline with the stages it is made of.
     *
     * @return Result Contains the list of PipelineResponseDTO on success.
     */
    public function getPipelines(): Result
    {
        try {
            $pipelines = $this->pipelineQuery->getAllWithStages();

            // Entities become response DTOs here, so nothing downstream of the
            // Service ever sees a domain object.
            $pipelineResponseDTOs = array_map(
                fn(PipelineDetailData $pipelineDetail) => $this->responseMapper->toResponseDTO($pipelineDetail->pipeline),
                $pipelines,
            );

            return Result::success(
                data: $pipelineResponseDTOs,
                message: 'Pipelines retrieved successfully.',
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve pipelines.', ['exception' => $exception]);

            return Result::failure('Failed to retrieve pipelines.', self::ERROR_INDEX_FAILED);
        }
    }

    /**
     * Returns one pipeline together with the stages it is made of.
     *
     * @param string $id
     *
     * @return Result Contains the PipelineDetailResponseDTO on success.
     */
    public function getPipelineDetails(string $id): Result
    {
        try {
            $pipelineDetail = $this->pipelineQuery->findDetailById((int) $id);

            // A missing pipeline is an ordinary outcome of a read, so it is
            // reported rather than thrown.
            if ($pipelineDetail === null) {
                return Result::failure('Pipeline not found.', self::ERROR_NOT_FOUND);
            }

            return Result::success(
                data: $this->responseMapper->toDetailResponseDTO($pipelineDetail),
                message: 'Pipeline retrieved successfully.',
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve pipeline.', ['exception' => $exception]);

            return Result::failure('Failed to retrieve pipeline.', self::ERROR_DETAIL_FAILED);
        }
    }

    /**
     * Returns the Kanban board for one pipeline: its stages, and the deals
     * sitting in each.
     *
     * @param string $id
     *
     * @return Result Contains the KanbanBoardResponseDTO on success.
     */
    public function getKanbanBoard(string $id): Result
    {
        try {
            $kanbanBoard = $this->pipelineQuery->findKanbanBoardById((int) $id);

            // A missing pipeline is an ordinary outcome of a read, so it is
            // reported rather than thrown.
            if ($kanbanBoard === null) {
                return Result::failure('Pipeline not found.', self::ERROR_NOT_FOUND);
            }

            return Result::success(
                data: $this->kanbanResponseMapper->toResponseDTO($kanbanBoard),
                message: 'Pipeline Kanban data retrieved successfully.',
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve the pipeline Kanban board.', ['exception' => $exception]);

            return Result::failure('Failed to retrieve the pipeline Kanban board.', self::ERROR_DETAIL_FAILED);
        }
    }
}
