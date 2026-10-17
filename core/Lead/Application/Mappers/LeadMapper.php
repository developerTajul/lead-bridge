<?php

declare(strict_types=1);

namespace Core\Lead\Application\Mappers;

use Core\Lead\Application\Contracts\LeadMapperContract;
use Core\Lead\Application\DTOs\Requests\LeadCreateDTO;
use Core\Lead\Application\DTOs\Requests\LeadFilterDTO;
use Core\Lead\Domain\Enums\LeadStatus;
use Core\Lead\Domain\LeadRules;
use InvalidArgumentException;

/**
 * Maps validated request input to Lead DTOs.
 *
 * @implements \Core\Lead\Application\Contracts\LeadMapperContract
 */
final class LeadMapper implements LeadMapperContract
{
    private const DEFAULT_PER_PAGE = 15;

    /**
     * Builds a LeadCreateDTO from validated input.
     *
     * @param array $validatedData
     *
     * @return LeadCreateDTO
     */
    public function mapToCreateDTO(array $validatedData): LeadCreateDTO
    {
        return new LeadCreateDTO(
            name: (string) $validatedData['name'],
            email: $validatedData['email'] ?? null,
            phone: $validatedData['phone'] ?? null,
            companyName: $validatedData['company_name'] ?? null,
            status: LeadStatus::tryFrom((string) ($validatedData['status'] ?? 'new')) ?? LeadStatus::NEW,
            source: (string) ($validatedData['source'] ?? 'website'),
        );
    }

    /**
     * Builds a LeadFilterDTO from query input, rejecting unrecognised values.
     *
     * @param array $queryParams
     *
     * @return LeadFilterDTO
     *
     * @throws InvalidArgumentException When a supplied value is not usable.
     */
    public function mapToFilterDTO(array $queryParams): LeadFilterDTO
    {
        return new LeadFilterDTO(
            status: $this->toEnum(LeadStatus::class, $queryParams['status'] ?? null, 'status'),
            source: $this->toText($queryParams['source'] ?? null, 'source'),
            search: $this->toText($queryParams['search'] ?? null, 'search'),
            perPage: $this->toPerPage($queryParams['per_page'] ?? self::DEFAULT_PER_PAGE),
        );
    }

    /**
     * Builds a LeadFilterDTO for user-facing screens, dropping and reporting
     * any value it cannot convert.
     *
     * @param array $queryParams
     *
     * @return array{0: LeadFilterDTO, 1: array<int, string>}
     */
    public function mapToFilterDTOLenient(array $queryParams): array
    {
        $ignored = [];

        return [
            new LeadFilterDTO(
                status: $this->toEnumOrNull(LeadStatus::class, $queryParams['status'] ?? null, 'status', $ignored),
                source: $this->toTextOrNull($queryParams['source'] ?? null, 'source', $ignored),
                search: $this->toTextOrNull($queryParams['search'] ?? null, 'search', $ignored),
                perPage: $this->toPerPageOrDefault($queryParams, $ignored),
            ),
            $ignored,
        ];
    }

    /**
     * Builds the status options for the list filter select.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function toStatusOptions(): array
    {
        return array_map(
            fn(LeadStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            LeadStatus::cases(),
        );
    }

    /**
     * Reads the page size, falling back to the default. An absent value is not
     * a dropped one, so only a supplied value that cannot be used is reported
     * as ignored - both an unparseable one and one outside the allowed bounds.
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
     * @param mixed              $value
     * @param string             $field
     * @param array<int, string> $ignored Collects the field when dropped.
     *
     * @return string|null
     */
    private function toTextOrNull(mixed $value, string $field, array &$ignored): ?string
    {
        try {
            return $this->toText($value, $field);
        } catch (InvalidArgumentException) {
            $ignored[] = $field;

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
     * Converts a free-text filter value. A blank value means "no filter"
     * rather than an empty match.
     *
     * @param mixed  $value
     * @param string $field
     *
     * @return string|null
     *
     * @throws InvalidArgumentException When the value is not text.
     */
    private function toText(mixed $value, string $field): ?string
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

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Converts a page size, rejecting anything outside the bounds the domain
     * filter accepts. The bound is checked here so an out-of-range value fails
     * as bad input rather than as a domain exception inside the Service.
     *
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

        $pageSize = (int) $perPage;

        if ($pageSize < LeadRules::MIN_PER_PAGE || $pageSize > LeadRules::MAX_PER_PAGE) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported value [%s] for filter [per_page]. Page size must be between %d and %d.',
                (string) $pageSize,
                LeadRules::MIN_PER_PAGE,
                LeadRules::MAX_PER_PAGE,
            ));
        }

        return $pageSize;
    }
}
