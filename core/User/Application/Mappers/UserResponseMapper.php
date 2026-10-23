<?php

declare(strict_types=1);

namespace Core\User\Application\Mappers;

use Core\User\Application\DTOs\Responses\UserResponseDTO;
use Core\User\Domain\Entities\UserEntity;

/**
 * Maps UserEntity to UserResponseDTO.
 *
 * That this response never carries the password or the remember token is the
 * entity's decision, not the DTO's.
 */
final class UserResponseMapper
{
    /**
     * Builds the response DTO from a stored user.
     *
     * @param UserEntity $user
     *
     * @return UserResponseDTO
     */
    public function toResponseDTO(UserEntity $user): UserResponseDTO
    {
        return new UserResponseDTO(
            id: (int) $user->id,
            name: $user->name,
            email: $user->email,
            image: $user->image,
            role: $user->role->value,
            status: $user->status->value,
        );
    }
}