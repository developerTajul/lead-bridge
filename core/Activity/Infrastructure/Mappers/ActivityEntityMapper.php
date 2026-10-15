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
        // ১. কোর ডেটা দিয়ে এন্টিটি রিকনস্টিটিউট (Reconstitute) করা হচ্ছে
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

        // ২. রিলেশনশিপ এবং টাইমস্ট্যাম্পের ডেটা থাকলে সেটার (Setter) ব্যবহার করে সেট করা হচ্ছে

        if (isset($data['user'])) {
            $entity->setUser($data['user']);
        }

        if (isset($data['trackable'])) {
            $entity->setTrackable($data['trackable']);
        }

        if (!empty($data['created_at'])) {
            $entity->setCreatedAt(new \DateTimeImmutable($data['created_at']));
        }

        if (!empty($data['updated_at'])) {
            $entity->setUpdatedAt(new \DateTimeImmutable($data['updated_at']));
        }

        return $entity;
    }
}