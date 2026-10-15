<?php

declare(strict_types=1);

namespace Core\Activity\Domain\ValueObjects;

use Core\Activity\Domain\Exceptions\InvalidActivityException;

/**
 * The type of record an activity is attached to, as a morph string.
 */
final readonly class TrackableType
{
    private function __construct(private string $value) {}

    /**
     * Builds the value object from a morph string.
     *
     * @param string $value
     *
     * @return self
     *
     * @throws InvalidActivityException When the value is blank.
     */
    public static function fromString(string $value): self
    {
        if (trim($value) === '') {
            throw InvalidActivityException::emptyTrackableType();
        }

        return new self($value);
    }

    /**
     * Returns the morph string as stored.
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Whether both value objects hold the same morph string.
     *
     * @param self $other
     *
     * @return bool
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
