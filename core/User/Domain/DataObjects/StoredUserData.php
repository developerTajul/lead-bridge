<?php

declare(strict_types=1);

namespace Core\User\Domain\DataObjects;

/**
 * One user as storage holds it.
 *
 * Carries no password hash: the read side never needs one, and a read model
 * that cannot hold a credential cannot leak one into a response.
 */
final readonly class StoredUserData
{
    /**
     * @param int         $id
     * @param string      $name
     * @param string      $email
     * @param string|null $image
     * @param string      $role
     * @param string      $status
     * @param string|null $createdAt Stored timestamp, ISO-8601 on the way out.
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
}
