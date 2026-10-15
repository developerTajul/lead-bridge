<?php

declare(strict_types=1);

namespace Core\Activity\Application\Contracts;

use Core\Activity\Application\DTOs\Requests\ActivityCreateDTO;
use Core\Activity\Application\DTOs\Requests\ActivityUpdateDTO;

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

    /**
     * Builds an ActivityUpdateDTO from validated input.
     *
     * @param int   $id            The activity ID from the route.
     * @param array $validatedData The validated input data from the delivery layer.
     *
     * @return ActivityUpdateDTO
     */
    public function mapToUpdateDTO(int $id, array $validatedData): ActivityUpdateDTO;
}