<?php

declare(strict_types=1);

namespace Core\Contact\Domain\ValueObjects;

use Core\Contact\Domain\ContactRules;
use Core\Contact\Domain\Exceptions\InvalidContactException;

/**
 * The company a contact is attached to, together with the role it holds there.
 */
final readonly class CompanyLink
{
    /**
     * @param int         $companyId
     * @param string|null $jobTitle
     * @param bool        $isPrimary
     */
    private function __construct(
        public int $companyId,
        public ?string $jobTitle,
        public bool $isPrimary,
    ) {}

    /**
     * Builds a link, guarding the values that reach the pivot table.
     *
     * @param int         $companyId
     * @param string|null $jobTitle
     * @param bool        $isPrimary
     *
     * @return self
     *
     * @throws InvalidContactException When the company ID is not a usable one, or the job title is too long.
     */
    public static function create(int $companyId, ?string $jobTitle, bool $isPrimary): self
    {
        if ($companyId <= 0) {
            throw InvalidContactException::invalidCompanyId($companyId);
        }

        if ($jobTitle !== null && mb_strlen($jobTitle) > ContactRules::MAX_JOB_TITLE_LENGTH) {
            throw InvalidContactException::jobTitleTooLong(mb_strlen($jobTitle));
        }

        return new self(
            companyId: $companyId,
            jobTitle: $jobTitle,
            isPrimary: $isPrimary,
        );
    }
}
