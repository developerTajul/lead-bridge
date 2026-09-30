<?php

declare(strict_types=1);

namespace Core\User\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a user cannot be found by the repository.
 */
final class UserNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('User with ID %d not found.', $id));
    }
}
