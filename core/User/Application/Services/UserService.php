<?php

declare(strict_types=1);

namespace Core\User\Application\Services;

use Core\Auth\Domain\Enums\UserRole;
use Core\Auth\Domain\Enums\UserStatus;
use Core\User\Application\DTOs\Requests\UserCreateDTO;
use Core\User\Application\DTOs\Requests\UserUpdateDTO;
use Core\User\Application\Mappers\UserResponseMapper;
use Core\User\Domain\Contracts\UserRepositoryContract;
use Core\User\Domain\DataObjects\UserData;
use Core\User\Domain\Entities\UserEntity;
use Core\Shared\Application\DTOs\Result;
use Core\Shared\Domain\Contracts\FileUploaderContract;
use Core\Shared\Domain\Contracts\PasswordHasherContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Psr\Log\LoggerInterface;

/**
 * Handles user use cases. Returns Result objects for success/failure.
 */
final class UserService
{
    private const IMAGE_DIRECTORY = 'users';

    /**
     * @param UserRepositoryContract     $userRepository
     * @param UserResponseMapper         $mapper
     * @param TransactionManagerContract $transactionManager
     * @param LoggerInterface            $logger
     * @param FileUploaderContract       $fileUploader
     * @param PasswordHasherContract     $passwordHasher
     */
    public function __construct(
        private readonly UserRepositoryContract $userRepository,
        private readonly UserResponseMapper $mapper,
        private readonly TransactionManagerContract $transactionManager,
        private readonly LoggerInterface $logger,
        private readonly FileUploaderContract $fileUploader,
        private readonly PasswordHasherContract $passwordHasher,
    ) {}

    /**
     * Returns a paginated list of users, scoped by the requester's role.
     *
     * Librarians see Members only; Admins see all roles.
     *
     * @param int      $perPage         Items per page (default: 10).
     * @param UserRole $requestedByRole Role of the authenticated requester.
     *
     * @return Result Contains a PaginatedResult of UserResponseDTO on success.
     */
    public function getUsers(int $perPage = 10, UserRole $requestedByRole): Result
    {
        try {
            $roleFilter = $requestedByRole === UserRole::LIBRARIAN
                ? UserRole::MEMBER
                : null;

            $paginatedEntities = $this->userRepository->paginate($perPage, $roleFilter);

            $paginatedResponse = $paginatedEntities->map(
                fn(\Core\User\Domain\Entities\UserEntity $entity) => $this->mapper->toResponseDTO($entity),
            );

            return Result::success(data: $paginatedResponse);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve users.', ['exception' => $exception]);

            return Result::failure(
                message: 'Failed to retrieve users. Please try again.',
                errorCode: 'USER_INDEX_FAILED',
            );
        }
    }

    /**
     * Returns a single user by ID.
     *
     * @param int $id The user ID.
     *
     * @return Result Contains the UserResponseDTO on success.
     */
    public function getUserById(int $id): Result
    {
        try {
            $userEntity = $this->userRepository->findById($id);

            if (!$userEntity) {
                return Result::failure(
                    message: "User not found with ID: {$id}",
                    errorCode: 'USER_NOT_FOUND',
                );
            }

            $responseDTO = $this->mapper->toResponseDTO($userEntity);

            return Result::success(
                data: $responseDTO,
                message: 'User retrieved successfully.',
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to fetch user.', ['exception' => $exception]);

            return Result::failure(
                message: 'We couldn\'t retrieve the user right now. Please try again.',
                errorCode: 'USER_FETCH_FAILED',
            );
        }
    }

    /**
     * Updates an existing user inside a transaction.
     * Cleans up old image on failure; replaces only if new image provided.
     *
     * @param UserUpdateDTO $userUpdateDTO
     *
     * @return Result Contains the UserResponseDTO on success.
     */
    public function updateUser(UserUpdateDTO $userUpdateDTO): Result
    {
        $oldImagePath = null;
        $newImagePath = null;

        try {
            $this->transactionManager->beginTransaction();

            // Use findByIdForUpdate for row-level locking (TOCTOU prevention).
            $existingUser = $this->userRepository->findByIdForUpdate($userUpdateDTO->id);
            if (!$existingUser) {
                $this->transactionManager->rollback();
                return Result::failure(
                    message: "User not found with ID: {$userUpdateDTO->id}",
                    errorCode: 'USER_NOT_FOUND',
                );
            }
            $oldImagePath = $existingUser->image;

            // Handle new image upload if provided.
            if ($userUpdateDTO->image !== null) {
                $newImagePath = $this->fileUploader->upload(
                    file: $userUpdateDTO->image,
                    directory: self::IMAGE_DIRECTORY,
                );
            }

            $password = $userUpdateDTO->password !== null && $userUpdateDTO->password !== ''
                ? $this->passwordHasher->hash($userUpdateDTO->password)
                : $existingUser->password;

            $userData = new UserData(
                name: $userUpdateDTO->name,
                email: $userUpdateDTO->email,
                password: $password,
                image: $newImagePath ?? $oldImagePath,
                role: $userUpdateDTO->role,
                status: $userUpdateDTO->status,
            );

            $userEntity = $this->userRepository->update($userUpdateDTO->id, $userData);

            if (!$userEntity) {
                $this->transactionManager->rollback();
                if ($newImagePath !== null) {
                    $this->fileUploader->delete($newImagePath);
                }
                return Result::failure(
                    message: "User not found with ID: {$userUpdateDTO->id}",
                    errorCode: 'USER_NOT_FOUND',
                );
            }

            $responseDTO = $this->mapper->toResponseDTO($userEntity);
            $this->transactionManager->commit();

            // Clean up old image if replaced.
            if ($newImagePath !== null && $oldImagePath !== null && $newImagePath !== $oldImagePath) {
                $this->fileUploader->delete($oldImagePath);
            }

            return Result::success(data: $responseDTO, message: 'User updated successfully.');
        } catch (\ValueError $exception) {
            $this->transactionManager->rollback();
            $this->logger->error('User update failed: invalid enum value.', [
                'exception'     => $exception->getMessage(),
                'role_value'    => $userUpdateDTO->role->value ?? null,
                'status_value'  => $userUpdateDTO->status->value ?? null,
            ]);
            if ($newImagePath !== null) {
                $this->fileUploader->delete($newImagePath);
            }
            return Result::failure(
                message: 'An invalid role or status was provided. Please select valid values and try again.',
                errorCode: 'USER_UPDATE_INVALID_ENUM',
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();
            if ($newImagePath !== null) {
                $this->fileUploader->delete($newImagePath);
            }
            $this->logger->error('User update failed.', [
                'message' => $exception->getMessage(),
                'trace'   => $exception->getTraceAsString(),
            ]);
            return Result::failure(
                message: 'Failed to update the user. Please try again.',
                errorCode: 'USER_UPDATE_FAILED',
            );
        }
    }

    /**
     * Updates a user's account status (approve or suspend).
     *
     * @param int        $id     The user ID.
     * @param UserStatus $status The new account status.
     *
     * @return Result Contains the UserResponseDTO on success.
     */
    public function updateUserStatus(int $id, UserStatus $status): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            $existingUser = $this->userRepository->findByIdForUpdate($id);
            if (!$existingUser) {
                $this->transactionManager->rollback();

                return Result::failure(
                    message: "User not found with ID: {$id}",
                    errorCode: 'USER_NOT_FOUND',
                );
            }

            $userEntity = $this->userRepository->updateStatus($id, $status);

            if (!$userEntity) {
                $this->transactionManager->rollback();

                return Result::failure(
                    message: "User not found with ID: {$id}",
                    errorCode: 'USER_NOT_FOUND',
                );
            }

            $responseDTO = $this->mapper->toResponseDTO($userEntity);

            $this->transactionManager->commit();

            return Result::success(data: $responseDTO, message: 'User status updated successfully.');
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();
            $this->logger->error('User status update failed.', [
                'message' => $exception->getMessage(),
                'trace'   => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to update the user status. Please try again.',
                errorCode: 'USER_STATUS_UPDATE_FAILED',
            );
        }
    }

    /**
     * Deletes a user by ID and cleans up associated profile image.
     *
     * @param int $id The user ID.
     *
     * @return Result Contains no data on success.
     */
    public function deleteUser(int $id): Result
    {
        try {
            $userEntity = $this->userRepository->findById($id);

            if (!$userEntity) {
                return Result::failure(
                    message: "User not found with ID: {$id}",
                    errorCode: 'USER_NOT_FOUND',
                );
            }

            $isDeleted = $this->userRepository->delete($id);

            if (!$isDeleted) {
                return Result::failure(
                    message: 'Failed to delete the user. It might be in use.',
                    errorCode: 'USER_DELETE_FAILED',
                );
            }

            // Clean up profile image after confirmed DB deletion
            if ($userEntity->image !== null) {
                $this->fileUploader->delete($userEntity->image);
            }

            return Result::success(
                data: null,
                message: 'User deleted successfully.',
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                "Failed to delete user [ID: {$id}]: " . $exception->getMessage(),
                ['exception' => $exception],
            );

            return Result::failure(
                message: "We couldn't delete the user right now. Please try again.",
                errorCode: 'USER_DELETE_FAILED',
            );
        }
    }

    /**
     * Creates a new user. Handles image upload and password hashing
     * via the Shared ports, wraps the operation in a transaction,
     * and cleans up the uploaded file on failure.
     *
     * @param UserCreateDTO $userCreateDTO
     *
     * @return Result Contains the UserResponseDTO on success.
     */
    public function createUser(UserCreateDTO $userCreateDTO): Result
    {
        $uploadedImagePath = null;

        try {
            $this->transactionManager->beginTransaction();

            if ($userCreateDTO->image !== null) {
                $uploadedImagePath = $this->fileUploader->upload(
                    file: $userCreateDTO->image,
                    directory: self::IMAGE_DIRECTORY,
                );

                $userCreateDTO = $userCreateDTO->withImage($uploadedImagePath);
            }

            $userData = new UserData(
                name: $userCreateDTO->name,
                email: $userCreateDTO->email,
                password: $this->passwordHasher->hash($userCreateDTO->password),
                image: $userCreateDTO->image,
                role: $userCreateDTO->role,
                status: $userCreateDTO->status,
            );

            $userEntity = $this->userRepository->create($userData);

            $responseDTO = $this->mapper->toResponseDTO($userEntity);

            $this->transactionManager->commit();

            return Result::success(data: $responseDTO, message: 'User created successfully.');
        } catch (\ValueError $exception) {
            // Domain enum validation failure — malformed data should never
            // reach this point after request validation, but guard defensively.
            $this->transactionManager->rollback();

            if ($uploadedImagePath !== null) {
                $this->fileUploader->delete($uploadedImagePath);
            }

            $this->logger->error('User creation failed: invalid enum value.', [
                'exception'   => $exception->getMessage(),
                'role_value'  => $userCreateDTO->role->value ?? null,
                'status_value' => $userCreateDTO->status->value ?? null,
            ]);

            return Result::failure(
                message: 'An invalid role or status was provided. Please select valid values and try again.',
                errorCode: 'USER_CREATE_INVALID_ENUM',
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            if ($uploadedImagePath !== null) {
                $this->fileUploader->delete($uploadedImagePath);
            }

            $this->logger->error('User creation failed.', [
                'message'   => $exception->getMessage(),
                'trace'     => $exception->getTraceAsString(),
            ]);

            return Result::failure(
                message: 'Failed to create the user. Please try again.',
                errorCode: 'USER_CREATE_FAILED',
            );
        }
    }
}
