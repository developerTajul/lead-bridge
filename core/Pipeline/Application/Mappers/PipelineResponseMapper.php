<?php

declare(strict_types=1);

namespace Core\Pipeline\Application\Mappers;

use Core\Pipeline\Application\DTOs\Responses\PipelineDetailResponseDTO;
use Core\Pipeline\Application\DTOs\Responses\PipelineResponseDTO;
use Core\Pipeline\Application\DTOs\Responses\PipelineStageDetailResponseDTO;
use Core\Pipeline\Application\DTOs\Responses\PipelineStageResponseDTO;
use Core\Pipeline\Domain\DataObjects\PipelineDetailData;
use Core\Pipeline\Domain\Entities\PipelineEntity;
use Core\Pipeline\Domain\ValueObjects\PipelineStage;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Maps a pipeline to PipelineResponseDTO.
 */
final class PipelineResponseMapper
{
    /**
     * Builds the response DTO from the stored pipeline and its stages, in the
     * order they are worked through. Timestamps are stored as UTC wall-clock
     * strings and leave on the same ISO-8601 wire format the other modules use,
     * so a client parses every module's dates alike.
     *
     * @param PipelineEntity $pipeline
     *
     * @return PipelineResponseDTO
     */
    public function toResponseDTO(PipelineEntity $pipeline): PipelineResponseDTO
    {
        return new PipelineResponseDTO(
            id: (int) $pipeline->id,
            name: $pipeline->name,
            is_default: $pipeline->isDefault,
            stages: array_map(
                $this->toStageDTO(...),
                $pipeline->orderedStages(),
            ),
            created_at: $this->toAtom($pipeline->createdAt),
            updated_at: $this->toAtom($pipeline->updatedAt),
        );
    }

    /**
     * Builds the detail response DTO from the pipeline read model: the
     * pipeline's own fields plus its stages. This is the one place the
     * flattened stage rows the repository returns become nested DTOs.
     *
     * @param PipelineDetailData $pipelineDetail
     *
     * @return PipelineDetailResponseDTO
     */
    public function toDetailResponseDTO(PipelineDetailData $pipelineDetail): PipelineDetailResponseDTO
    {
        $pipeline = $pipelineDetail->pipeline;

        return new PipelineDetailResponseDTO(
            id: (int) $pipeline->id,
            name: $pipeline->name,
            is_default: $pipeline->isDefault,
            stages: array_map($this->toStageDetailDTO(...), $pipelineDetail->stages),
            created_at: $this->toAtom($pipeline->createdAt),
            updated_at: $this->toAtom($pipeline->updatedAt),
        );
    }

    /**
     * @param array<string, mixed> $stage
     *
     * @return PipelineStageDetailResponseDTO
     */
    private function toStageDetailDTO(array $stage): PipelineStageDetailResponseDTO
    {
        return new PipelineStageDetailResponseDTO(
            id: (int) $stage['id'],
            name: (string) $stage['name'],
            order_position: (int) $stage['order_position'],
            probability: (int) $stage['probability'],
            created_at: $this->toAtom($stage['created_at'] ?? null),
            updated_at: $this->toAtom($stage['updated_at'] ?? null),
        );
    }

    /**
     * @param PipelineStage $stage
     *
     * @return PipelineStageResponseDTO
     */
    private function toStageDTO(PipelineStage $stage): PipelineStageResponseDTO
    {
        return new PipelineStageResponseDTO(
            id: (int) $stage->id,
            name: $stage->name,
            order_position: $stage->orderPosition,
            probability: $stage->probability,
        );
    }

    /**
     * Formats a stored UTC timestamp as ISO-8601. Parsing it in the application
     * timezone would shift the instant on every round trip.
     *
     * @param string|null $value
     *
     * @return string|null
     */
    private function toAtom(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->format(DateTimeInterface::ATOM);
    }
}
