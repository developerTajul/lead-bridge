<?php

declare(strict_types=1);

namespace Core\Activity\Infrastructure\Mappers;

use Core\Activity\Application\DTOs\Responses\ActivityProjectionDTO;
use Core\Activity\Domain\Entities\ActivityEntity;
use Core\Activity\Domain\Enums\ActivityType;

/**
 * Maps model arrays to ActivityEntity and to the activity read model.
 */
final class ActivityEntityMapper
{
    /**
     * The only relation fields exposed by the read model. A relation's raw
     * array also carries columns the response has no business returning
     * (a user's email or password hash, a lead's phone number).
     *
     * @var array<int, string>
     */
    private const RELATION_FIELDS = ['id', 'name'];

    /**
     * Builds a pure domain entity from a model array.
     *
     * @param array $data
     *
     * @return ActivityEntity
     */
    public function toEntity(array $data): ActivityEntity
    {
        return ActivityEntity::reconstitute(
            id: (int) $data['id'],
            trackableType: (string) $data['trackable_type'],
            trackableId: (int) $data['trackable_id'],
            type: ActivityType::from((string) $data['type']),
            subject: (string) $data['subject'],
            userId: isset($data['user_id']) ? (int) $data['user_id'] : null,
            description: $data['description'] ?? null,
            dueDate: !empty($data['due_date']) ? new \DateTimeImmutable($data['due_date']) : null,
            completedAt: !empty($data['completed_at']) ? new \DateTimeImmutable($data['completed_at']) : null
        );
    }

    /**
     * Builds the read model, pairing the entity with its loaded relations
     * and timestamps. Model::toArray() only exposes the relation keys when
     * they were eager-loaded.
     *
     * @param array $data
     *
     * @return ActivityProjectionDTO
     */
    public function toProjection(array $data): ActivityProjectionDTO
    {
        return new ActivityProjectionDTO(
            entity: $this->toEntity($data),
            user: $this->toRelationSummary($data['user'] ?? null),
            trackable: $this->toRelationSummary($data['trackable'] ?? null),
            createdAt: !empty($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            updatedAt: !empty($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null,
        );
    }

    /**
     * Narrows a loaded relation to the fields the response may expose, so
     * sensitive columns never leave this boundary.
     *
     * @param array|null $relation
     *
     * @return array<string, mixed>|null
     */
    private function toRelationSummary(?array $relation): ?array
    {
        if ($relation === null) {
            return null;
        }

        $summary = [];

        foreach (self::RELATION_FIELDS as $field) {
            if (array_key_exists($field, $relation)) {
                $summary[$field] = $relation[$field];
            }
        }

        return $summary;
    }
}
