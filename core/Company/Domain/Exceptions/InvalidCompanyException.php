<?php

declare(strict_types=1);

namespace Core\Company\Domain\Exceptions;

use Core\Company\Domain\CompanyRules;
use RuntimeException;

/**
 * Thrown when a company would be left in an invalid state.
 */
final class InvalidCompanyException extends RuntimeException
{
    /**
     * Reports a missing or blank name.
     *
     * @return self
     */
    public static function emptyName(): self
    {
        return new self('A company must carry a name.');
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
            'A company name cannot exceed %d characters, %d given.',
            CompanyRules::MAX_NAME_LENGTH,
            $length,
        ));
    }

    /**
     * Reports a website that is not an absolute URL.
     *
     * @param string $website
     *
     * @return self
     */
    public static function invalidWebsite(string $website): self
    {
        return new self(sprintf(
            'A company website must be a valid absolute URL, [%s] given.',
            $website,
        ));
    }

    /**
     * Reports a website longer than the website column accepts.
     *
     * @param int $length
     *
     * @return self
     */
    public static function websiteTooLong(int $length): self
    {
        return new self(sprintf(
            'A company website cannot exceed %d characters, %d given.',
            CompanyRules::MAX_WEBSITE_LENGTH,
            $length,
        ));
    }

    /**
     * Reports an industry longer than the industry column accepts.
     *
     * @param int $length
     *
     * @return self
     */
    public static function industryTooLong(int $length): self
    {
        return new self(sprintf(
            'A company industry cannot exceed %d characters, %d given.',
            CompanyRules::MAX_INDUSTRY_LENGTH,
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
            'A company phone number cannot exceed %d characters, %d given.',
            CompanyRules::MAX_PHONE_LENGTH,
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
            CompanyRules::MIN_PER_PAGE,
            CompanyRules::MAX_PER_PAGE,
            $perPage,
        ));
    }
}
