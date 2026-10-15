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
            id: $entity->getId(),
            user_id: $entity->getUserId(),
            trackable_type: $entity->getTrackableType(),
            trackable_id: $entity->getTrackableId(),
            type: $entity->getType()->value,
            subject: $entity->getSubject(),
            description: $entity->getDescription(),
            due_date: $entity->getDueDate()?->format('Y-m-d H:i:s'),
            completed_at: $entity->getCompletedAt()?->format('Y-m-d H:i:s'),
            created_at: $entity->getCreatedAt()?->format('Y-m-d\TH:i:s.000000\Z'),
            updated_at: $entity->getUpdatedAt()?->format('Y-m-d\TH:i:s.000000\Z'),
            user: $entity->getUser(),
            trackable: $entity->getTrackable()
        );
    }
}