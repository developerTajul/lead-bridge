<?php

declare(strict_types=1);

namespace Core\User\Domain\Exceptions;

use Core\Auth\Domain\Enums\AuthStatus;
use RuntimeException;

/**
 * Thrown when an account's status does not allow it to sign in.
 */
final class UserCannotAuthenticateException extends RuntimeException
{
    /**
     * Reports a sign-in attempt on an account that is not approved.
     *
     * @param AuthStatus $status The status that refused the sign-in.
     *
     * @return self
     */
    public static function fromStatus(AuthStatus $status): self
    {
        if ($status === AuthStatus::PENDING) {
            return new self('This account is awaiting approval and cannot sign in yet.');
        }

        if ($status === AuthStatus::SUSPENDED) {
            return new self('This account is suspended and cannot sign in.');
        }

        return new self(sprintf(
            'An account with status [%s] cannot sign in.',
            $status->value,
        ));
    }
}
