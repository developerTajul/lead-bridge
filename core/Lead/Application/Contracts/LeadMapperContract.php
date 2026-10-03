<?php

declare(strict_types=1);

namespace Core\Lead\Application\Contracts;

use Core\Lead\Application\DTOs\Requests\LeadCreateDTO;

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
}
