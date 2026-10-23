<?php

declare(strict_types=1);

namespace Core\User\Application\Mappers;

use Core\User\Application\DTOs\Responses\UserListItemResponseDTO;
use Core\User\Domain\DataObjects\StoredUserData;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Maps a stored user to the read side's response DTO.
 *
 * The write-side UserResponseMapper builds from a UserEntity and is left to the
 * write path; this one takes the read model so the list and detail reads never
 * depend on the Entity's shape.
 */
final class UserListResponseMapper
{
    /**
     * Builds the response DTO from a stored user. Timestamps leave on the same
     * ISO-8601 wire format the other modules use, so a client parses every
     * module's dates alike.
     *
     * @param StoredUserData $storedUser
     *
     * @return UserListItemResponseDTO
     */
    public function toResponseDTO(StoredUserData $storedUser): UserListItemResponseDTO
    {
        return new UserListItemResponseDTO(
            id: $storedUser->id,
            name: $storedUser->name,
            email: $storedUser->email,
            image: $storedUser->image,
            role: $storedUser->role,
            status: $storedUser->status,
            createdAt: $this->toAtom($storedUser->createdAt),
        );
    }

    /**
     * The stored timestamp as an ISO-8601 string. The column may arrive as a
     * string or as a date object, so both are accepted.
     *
     * @param mixed $value
     *
     * @return string|null
     */
    private function toAtom(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (new DateTimeImmutable((string) $value, new DateTimeZone('UTC')))
            ->format(DateTimeInterface::ATOM);
    }
}
