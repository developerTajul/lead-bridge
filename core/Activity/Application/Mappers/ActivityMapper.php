<?php

declare(strict_types=1);

namespace Core\Activity\Application\Mappers;

use Core\Activity\Application\Contracts\ActivityMapperContract;
use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Application\DTOs\Requests\ActivityFilterDTO;
use Core\Activity\Application\DTOs\Requests\ActivityUpdateDTO;
use Core\Activity\Domain\Enums\ActivityPeriod;
use Core\Activity\Domain\Enums\ActivityStatus;
use Core\Activity\Domain\Enums\ActivityType;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Maps validated request input to Activity DTOs.
 *
 * @implements \Core\Activity\Application\Contracts\ActivityMapperContract
 */
final class ActivityMapper implements ActivityMapperContract
{
    private const DEFAULT_PER_PAGE = 15;

    /**
     * Builds an ActivityCreateDTO from validated input.
     *
     * @param array $validatedData
     *
     * @return ActivityCreateDTO
     *
     * @throws InvalidArgumentException When a value cannot be converted.
     */
    public function mapToCreateDTO(array $validatedData): ActivityCreateDTO
    {
        return new ActivityCreateDTO(
            trackableType: (string) $validatedData['trackable_type'],
            trackableId: (int) $validatedData['trackable_id'],
            type: $this->toType($validatedData['type'] ?? null),
            subject: (string) $validatedData['subject'],
            userId: isset($validatedData['user_id']) ? (int) $validatedData['user_id'] : null,
            description: $validatedData['description'] ?? null,
            dueDate: $this->toDateTime($validatedData['due_date'] ?? null, 'due_date'),
            completedAt: $this->toDateTime($validatedData['completed_at'] ?? null, 'completed_at'),
        );
    }

    /**
     * Builds an ActivityUpdateDTO from validated input.
     *
     * @param int   $id            The activity ID from the route.
     * @param array $validatedData The validated input data from the delivery layer.
     *
     * @return ActivityUpdateDTO
     *
     * @throws InvalidArgumentException When a value cannot be converted.
     */
    public function mapToUpdateDTO(int $id, array $validatedData): ActivityUpdateDTO
    {
        // A key that is absent means "leave unchanged"; a key that is present
        // but null is an explicit request to clear that field.
        $clearsDueDate = array_key_exists('due_date', $validatedData)
            && $this->isBlank($validatedData['due_date']);
        $clearsDescription = array_key_exists('description', $validatedData)
            && $this->isBlank($validatedData['description']);

        return new ActivityUpdateDTO(
            id: $id,
            subject: $validatedData['subject'] ?? null,
            type: array_key_exists('type', $validatedData) && is_string($validatedData['type'])
                ? $this->toType($validatedData['type'])
                : null,
            description: $clearsDescription ? null : ($validatedData['description'] ?? null),
            dueDate: $this->toDateTime($validatedData['due_date'] ?? null, 'due_date'),
            completedAt: $this->toDateTime($validatedData['completed_at'] ?? null, 'completed_at'),
            reopen: $this->toBoolean($validatedData['reopen'] ?? false),
            clearDueDate: $clearsDueDate,
            clearDescription: $clearsDescription,
        );
    }

    /**
     * Whether a supplied value is an explicit request to clear the field.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    /**
     * Builds an ActivityFilterDTO from query input, rejecting unrecognised values.
     *
     * @param array $queryParams
     *
     * @return ActivityFilterDTO
     *
     * @throws InvalidArgumentException When a supplied value is not recognised.
     */
    public function mapToFilterDTO(array $queryParams): ActivityFilterDTO
    {
        return new ActivityFilterDTO(
            status: $this->toEnum(ActivityStatus::class, $queryParams['status'] ?? null, 'status'),
            type: $this->toEnum(ActivityType::class, $queryParams['type'] ?? null, 'type'),
            userId: $this->toUserId($queryParams['user_id'] ?? null),
            filter: $this->toEnum(ActivityPeriod::class, $queryParams['filter'] ?? null, 'filter'),
            perPage: $this->toPerPage($queryParams['per_page'] ?? self::DEFAULT_PER_PAGE),
        );
    }

    /**
     * Builds an ActivityFilterDTO for user-facing screens, dropping and
     * reporting any value it cannot convert.
     *
     * @param array $queryParams
     *
     * @return array{0: ActivityFilterDTO, 1: array<int, string>}
     */
    public function mapToFilterDTOLenient(array $queryParams): array
    {
        $ignored = [];

        return [
            new ActivityFilterDTO(
                status: $this->toEnumOrNull(ActivityStatus::class, $queryParams['status'] ?? null, 'status', $ignored),
                type: $this->toEnumOrNull(ActivityType::class, $queryParams['type'] ?? null, 'type', $ignored),
                userId: $this->toUserIdOrNull($queryParams['user_id'] ?? null, $ignored),
                filter: $this->toEnumOrNull(ActivityPeriod::class, $queryParams['filter'] ?? null, 'filter', $ignored),
                perPage: $this->toPerPageOrDefault($queryParams, $ignored),
            ),
            $ignored,
        ];
    }

    /**
     * Reads the page size, falling back to the default. An absent value is not
     * a dropped one, so only a supplied value that cannot be parsed is
     * reported as ignored.
     *
     * @param array              $queryParams
     * @param array<int, string> $ignored Collects the field when dropped.
     *
     * @return int
     */
    private function toPerPageOrDefault(array $queryParams, array &$ignored): int
    {
        if (! array_key_exists('per_page', $queryParams) || $queryParams['per_page'] === null || $queryParams['per_page'] === '') {
            return self::DEFAULT_PER_PAGE;
        }

        return $this->toPerPageOrNull($queryParams['per_page'], $ignored) ?? self::DEFAULT_PER_PAGE;
    }

    /**
     * @param class-string<\BackedEnum> $enum
     * @param mixed                     $value
     * @param string                    $field
     * @param array<int, string>        $ignored Collects the field when dropped.
     *
     * @return \BackedEnum|null
     */
    private function toEnumOrNull(
        string $enum,
        mixed $value,
        string $field,
        array &$ignored
    ): ?\BackedEnum {
        try {
            return $this->toEnum($enum, $value, $field);
        } catch (InvalidArgumentException) {
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
    private function toUserIdOrNull(mixed $userId, array &$ignored): ?int
    {
        try {
            return $this->toUserId($userId);
        } catch (InvalidArgumentException) {
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
    private function toPerPageOrNull(mixed $perPage, array &$ignored): ?int
    {
        try {
            return $this->toPerPage($perPage);
        } catch (InvalidArgumentException) {
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
     * @throws InvalidArgumentException When the value is not a case of the enum.
     */
    private function toEnum(string $enum, mixed $value, string $field): ?\BackedEnum
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported value [%s] for filter [%s].',
                is_scalar($value) ? (string) $value : get_debug_type($value),
                $field,
            ));
        }

        $case = $enum::tryFrom($value);

        if ($case === null) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported value [%s] for filter [%s].',
                $value,
                $field,
            ));
        }

        return $case;
    }

    /**
     * @param mixed $userId
     *
     * @return int|null
     *
     * @throws InvalidArgumentException When the value is not a positive integer.
     */
    private function toUserId(mixed $userId): ?int
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        if (! is_numeric($userId) || (int) $userId <= 0) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported value [%s] for filter [user_id].',
                is_scalar($userId) ? (string) $userId : get_debug_type($userId),
            ));
        }

        return (int) $userId;
    }

    /**
     * @param mixed $perPage
     *
     * @return int
     *
     * @throws InvalidArgumentException When the value is not a usable page size.
     */
    private function toPerPage(mixed $perPage): int
    {
        if (! is_numeric($perPage)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported value [%s] for filter [per_page].',
                is_scalar($perPage) ? (string) $perPage : get_debug_type($perPage),
            ));
        }

        return (int) $perPage;
    }

    /**
     * Converts an activity type, reporting unusable input as a bad request
     * rather than letting the enum's ValueError escape as a 500.
     *
     * @param mixed $value
     *
     * @return ActivityType
     *
     * @throws InvalidArgumentException When the value is not a known activity type.
     */
    private function toType(mixed $value): ActivityType
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported value [%s] for field [type].',
                is_scalar($value) ? (string) $value : get_debug_type($value),
            ));
        }

        $type = ActivityType::tryFrom($value);

        if ($type === null) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported value [%s] for field [type].',
                $value,
            ));
        }

        return $type;
    }

    /**
     * Parses a validated date value, so malformed input fails here rather
     * than inside the Service.
     *
     * @param mixed  $value
     * @param string $field
     *
     * @return DateTimeImmutable|null
     *
     * @throws InvalidArgumentException When the value cannot be parsed as a date.
     */
    private function toDateTime(mixed $value, string $field): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported value [%s] for field [%s].',
                is_scalar($value) ? (string) $value : get_debug_type($value),
                $field,
            ));
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception $exception) {
            throw new InvalidArgumentException(
                sprintf('Unsupported value [%s] for field [%s].', $value, $field),
                previous: $exception,
            );
        }
    }

    /**
     * Converts a request value to a real boolean. A cast would read the
     * string "false" as true.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function toBoolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
