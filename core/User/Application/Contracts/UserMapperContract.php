<?php

declare(strict_types=1);

namespace Core\User\Application\Contracts;

use Core\User\Application\DTOs\Requests\UserCreateDTO;
use Core\User\Application\DTOs\Requests\UserLoginDTO;
use Core\User\Application\DTOs\Requests\UserUpdateDTO;

/**
 * Defines how validated input maps to User create, login, and update DTOs.
 */
interface UserMapperContract
{
    /**
     * Builds a UserLoginDTO from validated login input.
     *
     * @param array $validatedData The validated login data.
     *
     * @return UserLoginDTO
     */
    public function mapToLoginDTO(array $validatedData): UserLoginDTO;

    /**
     * Builds a UserCreateDTO from validated self-registration input.
     *
     * @param array $validatedData The validated registration data.
     *
     * @return UserCreateDTO
     */
    public function mapToRegisterDTO(array $validatedData): UserCreateDTO;

    /**
     * Builds a UserCreateDTO from validated input.
     *
     * @param array $validatedData The validated input data from the delivery layer.
     *
     * @return UserCreateDTO
     */
    public function mapToCreateDTO(array $validatedData): UserCreateDTO;

    /**
     * Builds a UserUpdateDTO from validated input and the route-bound ID.
     *
     * @param int               $id            The user ID from the route parameter.
     * @param array             $validatedData The validated input data from the delivery layer.
     *
     * @return UserUpdateDTO
     */
    public function mapToUpdateDTO(int $id, array $validatedData): UserUpdateDTO;
}
