<?php

declare(strict_types=1);

namespace Core\Contact\Domain\Exceptions;

use Core\Contact\Domain\ContactRules;
use RuntimeException;

/**
 * Thrown when a contact would be left in an invalid state.
 */
final class InvalidContactException extends RuntimeException
{
    /**
     * Reports a missing or blank name.
     *
     * @return self
     */
    public static function emptyName(): self
    {
        return new self('A contact must carry a name.');
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
            'A contact name cannot exceed %d characters, %d given.',
            ContactRules::MAX_NAME_LENGTH,
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
        return new self('A contact must carry an email address.');
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
            'A contact email must be a valid email address, [%s] given.',
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
            'A contact email cannot exceed %d characters, %d given.',
            ContactRules::MAX_EMAIL_LENGTH,
            $length,
        ));
    }

    /**
     * Reports a phone number longer than the phone column accepts.
     *
     * @param int $length
     *
     * @return self
     */
    public static function phoneTooLong(int $length): self
    {
        return new self(sprintf(
            'A contact phone number cannot exceed %d characters, %d given.',
            ContactRules::MAX_PHONE_LENGTH,
            $length,
        ));
    }

    /**
     * Reports a page size outside the bounds the list accepts.
     *
     * @param int $perPage
     *
     * @return self
     */
    public static function invalidPerPage(int $perPage): self
    {
        return new self(sprintf(
            'Page size must be between %d and %d, %d given.',
            ContactRules::MIN_PER_PAGE,
            ContactRules::MAX_PER_PAGE,
            $perPage,
        ));
    }

    /**
     * Reports a company ID that cannot identify a company.
     *
     * @param int $companyId
     *
     * @return self
     */
    public static function invalidCompanyId(int $companyId): self
    {
        return new self(sprintf(
            'A linked company ID must be a positive integer, %d given.',
            $companyId,
        ));
    }

    /**
     * Reports a job title longer than the pivot column accepts.
     *
     * @param int $length
     *
     * @return self
     */
    public static function jobTitleTooLong(int $length): self
    {
        return new self(sprintf(
            'A contact job title cannot exceed %d characters, %d given.',
            ContactRules::MAX_JOB_TITLE_LENGTH,
            $length,
        ));
    }

    /**
     * Reports a company change that both attaches a company and drops the
     * stored one. The two cannot both happen, so the payload is refused.
     *
     * @return self
     */
    public static function contradictoryCompanyChange(): self
    {
        return new self(
            'A contact cannot be attached to a company and detached from its company at the same time.',
        );
    }
}
