<?php

declare(strict_types=1);

namespace Core\Lead\Application\Contracts;

use Core\Lead\Application\DTOs\Requests\LeadCreateDTO;
use Core\Lead\Application\DTOs\Requests\LeadFilterDTO;

/**
 * Defines how validated input maps to Lead DTOs.
 */
interface LeadMapperContract
{
    /**
     * Builds a LeadCreateDTO from validated input.
     *
     * @param array $validatedData The validated input data from the delivery layer.
     *
     * @return LeadCreateDTO
     */
    public function mapToCreateDTO(array $validatedData): LeadCreateDTO;

    /**
     * Builds a LeadFilterDTO from query string input, rejecting any value it
     * cannot convert. Used by the API, where bad input is a client error.
     *
     * @param array $queryParams
     *
     * @return LeadFilterDTO
     *
     * @throws \InvalidArgumentException When a supplied value is not usable.
     */
    public function mapToFilterDTO(array $queryParams): LeadFilterDTO;

    /**
     * Builds a LeadFilterDTO from query string input, dropping and reporting
     * any value it cannot convert, so a stale bookmark still renders a list.
     *
     * @param array $queryParams
     *
     * @return array{0: LeadFilterDTO, 1: array<int, string>} The filter and the dropped field names.
     */
    public function mapToFilterDTOLenient(array $queryParams): array;

    /**
     * Builds the status options for the list filter select.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function toStatusOptions(): array;
}
