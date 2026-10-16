<?php

declare(strict_types=1);

namespace Core\Activity\Application\DTOs\Requests;

use Core\Activity\Domain\Enums\ActivityPeriod;
use Core\Activity\Domain\Enums\ActivityStatus;
use Core\Activity\Domain\Enums\ActivityType;
use Core\Activity\Domain\Exceptions\InvalidActivityException;

/**
 * Raw activity list query input. Converts the string values from the request
 * into domain enums, and rejects anything unrecognised rather than ignoring it.
 */
readonly class ActivityFilterDTO
{
    public function __construct(
        public ?ActivityStatus $status = null,
        public ?ActivityType $type = null,
        public ?int $userId = null,
        public ?ActivityPeriod $filter = null,
        public int $perPage = 15
    ) {}

    /**
     * Builds the DTO from validated request input, rejecting unknown values.
     *
     * @param mixed $status
     * @param mixed $type
     * @param mixed $userId
     * @param mixed $filter
     * @param mixed $perPage
     *
     * @return self
     *
     * @throws InvalidActivityException When a supplied value is not recognised.
     */
    public static function fromRequest(
        mixed $status = null,
        mixed $type = null,
        mixed $userId = null,
        mixed $filter = null,
        mixed $perPage = 15
    ): self {
        return new self(
            status: self::toEnum(ActivityStatus::class, $status, 'status'),
            type: self::toEnum(ActivityType::class, $type, 'type'),
            userId: self::toUserId($userId),
            filter: self::toEnum(ActivityPeriod::class, $filter, 'filter'),
            perPage: self::toPerPage($perPage),
        );
    }

    /**
     * Builds the DTO from request input for user-facing screens, where a stale
     * bookmark or edited URL must not break the page. Values that cannot be
     * converted are dropped, and the dropped field names come back so the
     * caller can tell the user what it ignored.
     *
     * @param mixed $status
     * @param mixed $type
     * @param mixed $userId
     * @param mixed $filter
     * @param mixed $perPage
     *
     * @return array{self, array<int, string>} The DTO and the ignored fields.
     */
    public static function fromRequestLenient(
        mixed $status = null,
        mixed $type = null,
        mixed $userId = null,
        mixed $filter = null,
        mixed $perPage = 15
    ): array {
        $ignored = [];

        return [
            new self(
                status: self::toEnumOrNull(ActivityStatus::class, $status, 'status', $ignored),
                type: self::toEnumOrNull(ActivityType::class, $type, 'type', $ignored),
                userId: self::toUserIdOrNull($userId, $ignored),
                filter: self::toEnumOrNull(ActivityPeriod::class, $filter, 'filter', $ignored),
                perPage: self::toPerPageOrNull($perPage, $ignored) ?? 15,
            ),
            $ignored,
        ];
    }

    /**
     * @param class-string<\BackedEnum> $enum
     * @param mixed                     $value
     * @param string                    $field
     * @param array<int, string>        $ignored Collects the field when dropped.
     *
     * @return \BackedEnum|null
     */
    private static function toEnumOrNull(
        string $enum,
        mixed $value,
        string $field,
        array &$ignored
    ): ?\BackedEnum {
        try {
            return self::toEnum($enum, $value, $field);
        } catch (InvalidActivityException) {
            $ignored[] = $field;

            return null;
        }
    }

    /**
     * @param mixed              $userId
     * @param array<int, string> $ignored Collects the field when dropped.
     *
     * @return int|null
     */
    private static function toUserIdOrNull(mixed $userId, array &$ignored): ?int
    {
        try {
            return self::toUserId($userId);
        } catch (InvalidActivityException) {
            $ignored[] = 'user_id';

            return null;
        }
    }

    /**
     * @param mixed              $perPage
     * @param array<int, string> $ignored Collects the field when dropped.
     *
     * @return int|null Null when the value was dropped, so the caller applies its default.
     */
    private static function toPerPageOrNull(mixed $perPage, array &$ignored): ?int
    {
        try {
            return self::toPerPage($perPage);
        } catch (InvalidActivityException) {
            $ignored[] = 'per_page';

            return null;
        }
    }

    /**
     * @param class-string<\BackedEnum> $enum
     * @param mixed                     $value
     * @param string                    $field
     *
     * @return \BackedEnum|null
     *
     * @throws InvalidActivityException When the value is not a case of the enum.
     */
    private static function toEnum(string $enum, mixed $value, string $field): ?\BackedEnum
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw InvalidActivityException::invalidFilterValue($field, (string) $value);
        }

        $case = $enum::tryFrom($value);

        if ($case === null) {
            throw InvalidActivityException::invalidFilterValue($field, $value);
        }

        return $case;
    }

    /**
     * @param mixed $userId
     *
     * @return int|null
     *
     * @throws InvalidActivityException When the value is not a positive integer.
     */
    private static function toUserId(mixed $userId): ?int
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        if (! is_numeric($userId) || (int) $userId <= 0) {
            throw InvalidActivityException::invalidFilterValue('user_id', (string) $userId);
        }

        return (int) $userId;
    }

    /**
     * @param mixed $perPage
     *
     * @return int
     *
     * @throws InvalidActivityException When the value is not a usable page size.
     */
    private static function toPerPage(mixed $perPage): int
    {
        if (! is_numeric($perPage)) {
            throw InvalidActivityException::invalidFilterValue('per_page', (string) $perPage);
        }

        return (int) $perPage;
    }
}
