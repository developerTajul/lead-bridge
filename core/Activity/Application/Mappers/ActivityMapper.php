<?php

declare(strict_types=1);

namespace Core\Activity\Application\Mappers;

use Core\Activity\Application\Contracts\ActivityMapperContract;
use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Application\DTOs\Requests\ActivityUpdateDTO;
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

    /**
     * Builds an ActivityUpdateDTO from validated input.
     *
     * @param int   $id            The activity ID from the route.
     * @param array $validatedData The validated input data from the delivery layer.
     *
     * @return ActivityUpdateDTO
     */
    public function mapToUpdateDTO(int $id, array $validatedData): ActivityUpdateDTO
    {
        return new ActivityUpdateDTO(
            id: $id,
            subject: $validatedData['subject'] ?? null,
            type: array_key_exists('type', $validatedData) && is_string($validatedData['type'])
                ? ActivityType::from($validatedData['type'])
                : null,
            description: $validatedData['description'] ?? null,
            dueDate: $validatedData['due_date'] ?? null,
            completedAt: $validatedData['completed_at'] ?? null,
            reopen: (bool) ($validatedData['reopen'] ?? false),
        );
    }
}