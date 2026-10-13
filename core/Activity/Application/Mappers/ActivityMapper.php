<?php

declare(strict_types=1);

namespace Core\Activity\Application\Mappers;

use Core\Activity\Application\Contracts\ActivityMapperContract;
use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Domain\Enums\ActivityType;

/**
 * Maps validated request input to Activity DTOs.
 *
 * @implements \Core\Activity\Application\Contracts\ActivityMapperContract
 */
final class ActivityMapper implements ActivityMapperContract
{
    /**
     * Builds an ActivityCreateDTO from validated input.
     *
     * @param array $validatedData
     *
     * @return ActivityCreateDTO
     */
    public function mapToCreateDTO(array $validatedData): ActivityCreateDTO
    {
        return new ActivityCreateDTO(
            trackableType: (string) $validatedData['trackable_type'],
            trackableId: (int) $validatedData['trackable_id'],
            type: ActivityType::from((string) $validatedData['type']),
            subject: (string) $validatedData['subject'],
            userId: isset($validatedData['user_id']) ? (int) $validatedData['user_id'] : null,
            description: $validatedData['description'] ?? null,
            dueDate: $validatedData['due_date'] ?? null,
            completedAt: $validatedData['completed_at'] ?? null,
        );
    }
}