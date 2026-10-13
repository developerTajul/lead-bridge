<?php

declare(strict_types=1);

namespace Core\Activity\Application\Mappers;

use Core\Activity\Application\DTOs\Responses\ActivityResponseDTO;
use Core\Activity\Domain\Entities\ActivityEntity;

/**
 * Maps ActivityEntity to ActivityResponseDTO.
 */
final class ActivityResponseMapper
{
    public function toResponseDTO(ActivityEntity $entity): ActivityResponseDTO
    {
        return new ActivityResponseDTO(
            id: $entity->id,
            userId: $entity->userId,
            trackableType: $entity->trackableType,
            trackableId: $entity->trackableId,
            type: $entity->type->value,
            subject: $entity->subject,
            description: $entity->description,
            dueDate: $entity->dueDate,
            completedAt: $entity->completedAt,
            createdAt: $entity->createdAt,
            updatedAt: $entity->updatedAt,
        );
    }
}