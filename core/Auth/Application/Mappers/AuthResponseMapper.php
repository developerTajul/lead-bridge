<?php

declare(strict_types=1);

namespace Core\Auth\Application\Mappers;

use Core\Auth\Application\DTOs\Responses\AuthResponseDTO;
use Core\Auth\Domain\Entities\User;

/**
 * Maps a User entity to AuthResponseDTO.
 */
final class AuthResponseMapper
{
    public function toResponseDTO(User $entity): AuthResponseDTO
    {
        return new AuthResponseDTO(
            id: $entity->id,
            name: $entity->name,
            email: $entity->email,
            role: $entity->role->value,
            status: $entity->status->value,
        );
    }
}
