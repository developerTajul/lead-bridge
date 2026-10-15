<?php

declare(strict_types=1);

namespace Core\Activity\Application\Mappers;

use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Application\DTOs\Responses\ActivityResponseDTO;
use Core\Activity\Domain\Entities\ActivityEntity;

/**
 * Maps an activity and its loaded relations to ActivityResponseDTO.
 */
final class ActivityResponseMapper
{
    /**
     * Builds the response DTO from the activity projection.
     *
     * @param ActivityProjectionDTO $projection
     *
     * @return ActivityResponseDTO
     */
    public function toResponseDTO(ActivityProjectionDTO $projection): ActivityResponseDTO
    {
        $entity = $projection->entity;

        return new ActivityResponseDTO(
            id: $entity->getId(),
            user_id: $entity->getUserId(),
            trackable_type: $entity->getTrackableType()->toString(),
            trackable_id: $entity->getTrackableId(),
            type: $entity->getType()->value,
            subject: $entity->getSubject(),
            description: $entity->getDescription(),
            due_date: $entity->getDueDate()?->format('Y-m-d H:i:s'),
            completed_at: $entity->getCompletedAt()?->format('Y-m-d H:i:s'),
            created_at: $projection->createdAt?->format('Y-m-d\TH:i:s.000000\Z'),
            updated_at: $projection->updatedAt?->format('Y-m-d\TH:i:s.000000\Z'),
            user: $projection->user,
            trackable: $projection->trackable
        );
    }
}
