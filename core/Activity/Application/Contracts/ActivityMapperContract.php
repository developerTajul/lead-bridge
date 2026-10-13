<?php

declare(strict_types=1);

namespace Core\Activity\Application\Contracts;

use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;

/**
 * Defines how validated input maps to Activity DTOs.
 */
interface ActivityMapperContract
{
    /**
     * Builds an ActivityCreateDTO from validated input.
     *
     * @param array $validatedData The validated input data from the delivery layer.
     *
     * @return ActivityCreateDTO
     */
    public function mapToCreateDTO(array $validatedData): ActivityCreateDTO;
}