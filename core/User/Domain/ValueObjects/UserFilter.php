<?php

declare(strict_types=1);

namespace Core\User\Domain\ValueObjects;

use Core\User\Domain\Exceptions\InvalidUserException;
use Core\User\Domain\UserRules;

/**
 * Filter criteria the repository uses to narrow down the user list.
 * Validates itself, so the criteria reaching Infrastructure are always usable.
 */
final readonly class UserFilter
{
    /**
     * @param int $perPage
     *
     * @throws InvalidUserException When the page size falls outside its bounds.
     */
    public function __construct(
        public int $perPage = 15,
    ) {
        if ($perPage < UserRules::MIN_PER_PAGE || $perPage > UserRules::MAX_PER_PAGE) {
            throw InvalidUserException::invalidPerPage($perPage);
        }
    }
}
