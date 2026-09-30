<?php

declare(strict_types=1);

namespace Core\User\Application\Mappers;

use Core\User\Application\DTOs\Responses\UserResponseDTO;
use Core\User\Domain\Entities\UserEntity;

/**
 * Maps UserEntity to UserResponseDTO.
 */
final class UserResponseMapper
{
    public function toResponseDTO(UserEntity $entity): UserResponseDTO
    {
        return new UserResponseDTO(
            id: $entity->id,
            name: $entity->name,
            email: $entity->email,
            image: $entity->image,
            role: $entity->role->value,
            status: $entity->status->value,
        );
    }
}
