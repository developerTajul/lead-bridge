<?php

declare(strict_types=1);

namespace Core\Activity\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when an activity cannot be found by the repository.
 */
final class ActivityNotFoundException extends RuntimeException
{
    public function __construct(int $id)
    {
        parent::__construct(sprintf('Activity with ID %d not found.', $id));
    }
}