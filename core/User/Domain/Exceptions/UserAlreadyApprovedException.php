<?php

declare(strict_types=1);

namespace Core\User\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when an account that is already approved is approved again.
 */
final class UserAlreadyApprovedException extends RuntimeException
{
    /**
     * @param int $userId
     *
     * @return self
     */
    public static function withId(int $userId): self
    {
        return new self(sprintf(
            'User [%d] is already approved.',
            $userId,
        ));
    }
}
