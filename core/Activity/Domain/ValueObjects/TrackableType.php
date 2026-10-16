<?php

declare(strict_types=1);

namespace Core\Activity\Domain\ValueObjects;

use Core\Activity\Domain\Exceptions\InvalidActivityException;

/**
 * The type of record an activity is attached to, as a morph string.
 */
final readonly class TrackableType
{
    /**
     * The record types an activity may be attached to. Without this, any
     * class name from request input would be accepted as a morph target.
     *
     * @var array<int, string>
     */
    private const ALLOWED_TYPES = [
        'App\\Models\\Lead',
        'App\\Models\\Deal',
        'App\\Models\\Contact',
        'App\\Models\\Company',
    ];

    private function __construct(private string $value) {}

    /**
     * Builds the value object from a morph string.
     *
     * @param string $value
     *
     * @return self
     *
     * @throws InvalidActivityException When the value is blank or not an allowed record type.
     */
    public static function fromString(string $value): self
    {
        if (trim($value) === '') {
            throw InvalidActivityException::emptyTrackableType();
        }

        if (! in_array($value, self::ALLOWED_TYPES, true)) {
            throw InvalidActivityException::unsupportedTrackableType($value);
        }

        return new self($value);
    }

    /**
     * The record types that may be attached to an activity.
     *
     * @return array<int, string>
     */
    public static function allowedTypes(): array
    {
        return self::ALLOWED_TYPES;
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
