<?php

declare(strict_types=1);

namespace Core\User\Application\Mappers;

use Core\User\Application\DTOs\Requests\UserFilterDTO;
use Core\User\Domain\UserRules;
use InvalidArgumentException;

/**
 * Maps query input into the read side's filter DTO.
 */
final class UserListFilterMapper
{
    private const DEFAULT_PER_PAGE = 15;

    /**
     * Builds a UserFilterDTO from query input.
     *
     * @param array $queryParams
     *
     * @return UserFilterDTO
     */
    public function mapToFilterDTO(array $queryParams): UserFilterDTO
    {
        return new UserFilterDTO(
            perPage: $this->toPerPage($queryParams['per_page'] ?? self::DEFAULT_PER_PAGE),
        );
    }

    /**
     * Converts the page size, rejecting one the list does not accept.
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

        if ($pageSize < UserRules::MIN_PER_PAGE || $pageSize > UserRules::MAX_PER_PAGE) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported value [%s] for filter [per_page]. Page size must be between %d and %d.',
                (string) $pageSize,
                UserRules::MIN_PER_PAGE,
                UserRules::MAX_PER_PAGE,
            ));
        }

        return $pageSize;
    }
}
