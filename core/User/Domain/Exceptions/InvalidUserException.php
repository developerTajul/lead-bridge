<?php

declare(strict_types=1);

namespace Core\User\Domain\Exceptions;

use Core\User\Domain\UserRules;
use RuntimeException;

/**
 * Thrown when a user request would be left in an invalid state.
 */
final class InvalidUserException extends RuntimeException
{
    /**
     * Reports a user ID that cannot identify a user.
     *
     * @param int $userId
     *
     * @return self
     */
    public static function invalidUserId(int $userId): self
    {
        return new self(sprintf(
            'A user ID must be a positive integer, %d given.',
            $userId,
        ));
    }

    /**
     * Reports a page size outside the range the user list accepts.
     *
     * @param int $perPage
     *
     * @return self
     */
    public static function invalidPerPage(int $perPage): self
    {
        return new self(sprintf(
            'A user page size must be between %d and %d, %d given.',
            UserRules::MIN_PER_PAGE,
            UserRules::MAX_PER_PAGE,
            $perPage,
        ));
    }

    /**
     * Reports a missing or blank name.
     *
     * @return self
     */
    public static function emptyName(): self
    {
        return new self('A user must carry a name.');
    }

    /**
     * Reports a name longer than the name column accepts.
     *
     * @param int $length
     *
     * @return self
     */
    public static function nameTooLong(int $length): self
    {
        return new self(sprintf(
            'A user name cannot exceed %d characters, %d given.',
            UserRules::MAX_NAME_LENGTH,
            $length,
        ));
    }

    /**
     * Reports a missing or blank email address.
     *
     * @return self
     */
    public static function emptyEmail(): self
    {
        return new self('A user must carry an email address.');
    }

    /**
     * Reports an email that is not a usable address.
     *
     * @param string $email
     *
     * @return self
     */
    public static function invalidEmail(string $email): self
    {
        return new self(sprintf(
            'A user email must be a valid email address, [%s] given.',
            $email,
        ));
    }

    /**
     * Reports an email longer than the email column accepts.
     *
     * @param int $length
     *
     * @return self
     */
    public static function emailTooLong(int $length): self
    {
        return new self(sprintf(
            'A user email cannot exceed %d characters, %d given.',
            UserRules::MAX_EMAIL_LENGTH,
            $length,
        ));
    }

    /**
     * Reports a password that has not been hashed before it reached the entity.
     * A stored credential is never held in the clear.
     *
     * @return self
     */
    public static function passwordNotHashed(): self
    {
        return new self('A user password must be a hash, never a plain-text value.');
    }

    /**
     * Reports an account left with no password hash.
     *
     * @return self
     */
    public static function emptyPassword(): self
    {
        return new self('A user must carry a password hash.');
    }
}
