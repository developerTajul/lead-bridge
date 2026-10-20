<?php

declare(strict_types=1);

namespace Core\Contact\Domain;

/**
 * Holds the contact module's shared numeric rules.
 */
final class ContactRules
{
    /**
     * The largest page size a contact list may be asked for.
     */
    public const MAX_PER_PAGE = 100;

    /**
     * The smallest page size a contact list may be asked for.
     */
    public const MIN_PER_PAGE = 1;

    public const MAX_NAME_LENGTH = 255;

    public const MAX_EMAIL_LENGTH = 255;

    public const MAX_PHONE_LENGTH = 50;

    public const MAX_JOB_TITLE_LENGTH = 255;
}
