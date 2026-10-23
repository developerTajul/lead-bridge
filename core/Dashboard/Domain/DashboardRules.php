<?php

declare(strict_types=1);

namespace Core\Dashboard\Domain;

/**
 * Holds the dashboard module's shared numeric rules.
 */
final class DashboardRules
{
    /**
     * How many activity feed rows the dashboard shows at most. The feed is a
     * glance at what is next, not the activity list, so it is capped rather
     * than paginated.
     */
    public const FEED_LIMIT = 8;

    /**
     * How many stages the pipeline overview shows at most. A workspace with
     * more stages than this is better read on the board itself.
     */
    public const BOARD_LIMIT = 12;
}
