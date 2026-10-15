<?php

declare(strict_types=1);

namespace Core\Activity\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when an activity would be left in an invalid state.
 */
final class InvalidActivityException extends RuntimeException
{
    /**
     * Reports a missing or blank subject.
     *
     * @return self
     */
    public static function emptySubject(): self
    {
        return new self('An activity must carry a subject.');
    }

    /**
     * Reports a trackable record that was never identified.
     *
     * @param int $trackableId
     *
     * @return self
     */
    public static function invalidTrackableId(int $trackableId): self
    {
        return new self(sprintf('Trackable ID must be a positive integer, %d given.', $trackableId));
    }

    /**
     * Reports a missing trackable type.
     *
     * @return self
     */
    public static function emptyTrackableType(): self
    {
        return new self('An activity must reference a trackable type.');
    }

    /**
     * Reports a completion of an activity that is already completed.
     *
     * @return self
     */
    public static function alreadyCompleted(): self
    {
        return new self('The activity is already completed.');
    }

    /**
     * Reports a completion timestamp that lies in the future.
     *
     * @param \DateTimeImmutable $completedAt
     * @param \DateTimeImmutable $now
     *
     * @return self
     */
    public static function completionInTheFuture(\DateTimeImmutable $completedAt, \DateTimeImmutable $now): self
    {
        return new self(sprintf(
            'An activity cannot be completed in the future: %s given, current time is %s.',
            $completedAt->format('Y-m-d H:i:s'),
            $now->format('Y-m-d H:i:s'),
        ));
    }
}
