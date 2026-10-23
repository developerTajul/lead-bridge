<?php

declare(strict_types=1);

namespace Core\User\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when an account that is not approved is suspended.
 */
final class UserAlreadyActiveException extends RuntimeException
{
    /**
     * @param int $userId
     *
     * @return self
     */
    public static function withId(int $userId): self
    {
        return new self(sprintf(
            'User [%d] is not approved, so it cannot be suspended.',
            $userId,
        ));
    }
}
