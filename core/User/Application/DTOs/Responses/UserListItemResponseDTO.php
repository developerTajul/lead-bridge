<?php

declare(strict_types=1);

namespace Core\User\Application\DTOs\Responses;

use JsonSerializable;

/**
 * One user as the read side returns it.
 *
 * Kept separate from UserResponseDTO, which the write-side mapper builds from a
 * UserEntity: this one carries the created_at a listing column shows and never
 * carries a credential.
 */
final readonly class UserListItemResponseDTO implements JsonSerializable
{
    /**
     * @param int         $id
     * @param string      $name
     * @param string      $email
     * @param string|null $image
     * @param string      $role
     * @param string      $status
     * @param string|null $createdAt
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $image,
        public string $role,
        public string $status,
        public ?string $createdAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'image'      => $this->image,
            'role'       => $this->role,
            'status'     => $this->status,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
