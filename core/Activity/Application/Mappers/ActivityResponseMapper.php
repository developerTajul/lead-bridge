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
            trackableType: $entity->getTrackableType(),
            trackableId: $entity->getTrackableId(),
            type: $entity->getType()->value, // Enum থেকে স্ট্রিং ভ্যালু নেওয়া হলো
            subject: $entity->getSubject(),
            userId: $entity->getUserId(),
            description: $entity->getDescription(),
            dueDate: $entity->getDueDate()?->format('Y-m-d H:i:s'),
            completedAt: $entity->getCompletedAt()?->format('Y-m-d H:i:s')
        );
    }
}