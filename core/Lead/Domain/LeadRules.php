<?php

declare(strict_types=1);

namespace Core\Lead\Domain;

/**
 * Holds the lead module's shared numeric rules.
 */
final class LeadRules
{
    /**
     * The largest page size a lead list may be asked for.
     */
    public const MAX_PER_PAGE = 100;

    /**
     * The smallest page size a lead list may be asked for.
     */
    public const MIN_PER_PAGE = 1;

    public const DEFAULT_PER_PAGE = 15;
}
