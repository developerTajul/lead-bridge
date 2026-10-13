<?php

declare(strict_types=1);

namespace Core\Activity\Infrastructure\Mappers;

use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityType;

/**
 * Maps model arrays to ActivityEntity.
 */
final class ActivityEntityMapper
{
    public function toEntity(array $data): ActivityEntity
    {
        return new ActivityEntity(
            id: (int) $data['id'],
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            trackableType: (string) $data['trackable_type'],
            trackableId: (int) $data['trackable_id'],
            type: ActivityType::tryFrom((string) $data['type']) ?? ActivityType::TASK,
            subject: (string) $data['subject'],
            description: $data['description'] ?? null,
            dueDate: $data['due_date'] ?? null,
            completedAt: $data['completed_at'] ?? null,
            createdAt: (string) $data['created_at'],
            updatedAt: (string) $data['updated_at'],
        );
    }
}