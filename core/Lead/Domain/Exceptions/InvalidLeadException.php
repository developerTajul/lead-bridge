<?php

declare(strict_types=1);

namespace Core\Lead\Domain\Exceptions;

use Core\Lead\Domain\LeadRules;
use RuntimeException;

/**
 * Raised when lead input or criteria breaks a domain rule.
 */
final class InvalidLeadException extends RuntimeException
{
    /**
     * Builds the exception for a page size outside its bounds.
     *
     * @param int $perPage
     *
     * @return self
     */
    public static function invalidPerPage(int $perPage): self
    {
        return new self(sprintf(
            'Page size must be between %d and %d, got %d.',
            LeadRules::MIN_PER_PAGE,
            LeadRules::MAX_PER_PAGE,
            $perPage,
        ));
    }
}
