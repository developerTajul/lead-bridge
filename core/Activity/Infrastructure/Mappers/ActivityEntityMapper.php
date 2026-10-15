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
        $entity = ActivityEntity::reconstitute(
            id: (int) $data['id'],
            trackableType: (string) $data['trackable_type'],
            trackableId: (int) $data['trackable_id'],
            type: ActivityType::tryFrom((string) $data['type']) ?? ActivityType::TASK,
            subject: (string) $data['subject'],
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            description: $data['description'] ?? null,
            dueDate: !empty($data['due_date']) ? new \DateTimeImmutable($data['due_date']) : null,
            completedAt: !empty($data['completed_at']) ? new \DateTimeImmutable($data['completed_at']) : null
        );

        // Model::toArray() only exposes these keys when they were eager-loaded.
        return $entity->withProjection(
            user: $data['user'] ?? null,
            trackable: $data['trackable'] ?? null,
            createdAt: !empty($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            updatedAt: !empty($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null,
        );
    }
}
