<?php

declare(strict_types=1);

namespace Core\User\Application\Mappers;

use Core\Auth\Domain\Enums\UserRole;
use Core\Auth\Domain\Enums\UserStatus;
use Core\User\Application\Contracts\UserMapperContract;
use Core\User\Application\DTOs\Requests\UserCreateDTO;
use Core\User\Application\DTOs\Requests\UserLoginDTO;
use Core\User\Application\DTOs\Requests\UserUpdateDTO;

/**
 * Maps validated request input to User login/create/update DTOs.
 *
 * @implements \Core\User\Application\Contracts\UserMapperContract
 */
final class UserMapper implements UserMapperContract
{
    /**
     * Builds a UserLoginDTO from validated login input.
     *
     * @param array $validatedData
     *
     * @return UserLoginDTO
     */
    public function mapToLoginDTO(array $validatedData): UserLoginDTO
    {
        return new UserLoginDTO(
            email: (string) $validatedData['email'],
            password: (string) $validatedData['password'],
        );
    }

    /**
     * Builds a UserCreateDTO from validated self-registration input.
     * Self-registration always creates a pending Member.
     *
     * @param array $validatedData
     *
     * @return UserCreateDTO
     */
    public function mapToRegisterDTO(array $validatedData): UserCreateDTO
    {
        return new UserCreateDTO(
            name: (string) $validatedData['name'],
            email: (string) $validatedData['email'],
            password: (string) $validatedData['password'],
            image: $validatedData['image'] ?? null,
            role: UserRole::MEMBER,
            status: UserStatus::PENDING,
        );
    }
    /**
     * Builds a UserCreateDTO from validated input.
     *
     * @param array $validatedData
     *
     * @return UserCreateDTO
     */
    public function mapToCreateDTO(array $validatedData): UserCreateDTO
    {
        return new UserCreateDTO(
            name: $validatedData['name'],
            email: $validatedData['email'],
            password: $validatedData['password'],
            image: $validatedData['image'] ?? null,
            role: UserRole::tryFrom((string) $validatedData['role']) ?? UserRole::MEMBER,
            status: UserStatus::tryFrom((string) $validatedData['status']) ?? UserStatus::PENDING,
        );
    }

    /**
     * Builds a UserUpdateDTO from validated input and the route-bound ID.
     *
     * @param int   $id
     * @param array $validatedData
     *
     * @return UserUpdateDTO
     */
    public function mapToUpdateDTO(int $id, array $validatedData): UserUpdateDTO
    {
        return new UserUpdateDTO(
            id: $id,
            name: $validatedData['name'],
            email: $validatedData['email'],
            password: $validatedData['password'] ?? null,
            image: $validatedData['image'] ?? null,
            role: UserRole::tryFrom((string) $validatedData['role']) ?? UserRole::MEMBER,
            status: UserStatus::tryFrom((string) $validatedData['status']) ?? UserStatus::PENDING,
        );
    }
}
