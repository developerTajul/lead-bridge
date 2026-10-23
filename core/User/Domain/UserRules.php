<?php

declare(strict_types=1);

namespace Core\User\Domain;

/**
 * Holds the user module's shared numeric rules.
 */
final class UserRules
{
    /**
     * The page-size bounds the user list accepts.
     */
    public const MIN_PER_PAGE = 1;

    public const MAX_PER_PAGE = 100;

    /**
     * The widest name and email the users table's columns accept.
     */
    public const MAX_NAME_LENGTH = 255;

    public const MAX_EMAIL_LENGTH = 255;
}
