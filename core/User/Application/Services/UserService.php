<?php

declare(strict_types=1);

namespace Core\User\Application\Services;

use Core\Auth\Domain\Enums\AuthStatus;
use Core\User\Application\DTOs\Requests\UserCreateDTO;
use Core\User\Application\DTOs\Requests\UserLoginDTO;
use Core\User\Application\DTOs\Requests\UserUpdateDTO;
use Core\User\Application\Mappers\UserResponseMapper;
use Core\Shared\Application\DTOs\Result;
use Core\Shared\Domain\Contracts\FileUploaderContract;
use Core\Shared\Domain\Contracts\PasswordHasherContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Core\User\Domain\Contracts\UserRepositoryContract;
use Core\User\Domain\Entities\UserEntity;
use Core\User\Domain\Exceptions\InvalidUserException;
use Core\User\Domain\Exceptions\UserAlreadyActiveException;
use Core\User\Domain\Exceptions\UserAlreadyApprovedException;
use Core\User\Domain\Exceptions\UserCannotAuthenticateException;
use Psr\Log\LoggerInterface;

/**
 * Handles user write use cases: create, update, status change, delete, sign-in.
 */
final class UserService
{
    private const IMAGE_DIRECTORY = 'users';

    private const ERROR_INVALID = 'USER_INVALID';

    private const ERROR_NOT_FOUND = 'USER_NOT_FOUND';

    private const ERROR_ALREADY_APPROVED = 'USER_ALREADY_APPROVED';

    private const ERROR_ALREADY_ACTIVE = 'USER_ALREADY_ACTIVE';

    private const ERROR_CREATE_FAILED = 'USER_CREATE_FAILED';

    private const ERROR_UPDATE_FAILED = 'USER_UPDATE_FAILED';

    private const ERROR_STATUS_UPDATE_FAILED = 'USER_STATUS_UPDATE_FAILED';

    private const ERROR_DELETE_FAILED = 'USER_DELETE_FAILED';

    private const ERROR_LOGIN_FAILED = 'AUTH_LOGIN_FAILED';

    private const ERROR_INVALID_CREDENTIALS = 'AUTH_INVALID_CREDENTIALS';

    private const ERROR_PENDING = 'AUTH_PENDING';

    private const ERROR_SUSPENDED = 'AUTH_SUSPENDED';

    /**
     * @param UserRepositoryContract     $userRepository
     * @param UserResponseMapper         $responseMapper
     * @param TransactionManagerContract $transactionManager
     * @param LoggerInterface            $logger
     * @param FileUploaderContract       $fileUploader
     * @param PasswordHasherContract     $passwordHasher
     */
    public function __construct(
        private readonly UserRepositoryContract $userRepository,
        private readonly UserResponseMapper $responseMapper,
        private readonly TransactionManagerContract $transactionManager,
        private readonly LoggerInterface $logger,
        private readonly FileUploaderContract $fileUploader,
        private readonly PasswordHasherContract $passwordHasher,
    ) {}

    /**
     * Creates a user inside a transaction, uploading the image first and
     * removing it again if the write fails.
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
            }

            // The entity guards its own invariants; the service only hands it
            // already-hashed credentials and an already-stored image path.
            $userEntity = UserEntity::register(
                name: $userCreateDTO->name,
                email: $userCreateDTO->email,
                password: $this->passwordHasher->hash($userCreateDTO->password),
                image: $uploadedImagePath,
                role: $userCreateDTO->role,
                status: $userCreateDTO->status,
            );

            $savedUser = $this->userRepository->create($userEntity);
            $userResponseDTO = $this->responseMapper->toResponseDTO($savedUser);
            $this->transactionManager->commit();
        } catch (InvalidUserException $exception) {
            $this->transactionManager->rollback();
            $this->removeUploadedImage($uploadedImagePath);

            return Result::failure($exception->getMessage(), self::ERROR_INVALID);
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();
            $this->removeUploadedImage($uploadedImagePath);
            $this->logFailure('User creation failed.', $exception, []);

            return Result::failure('Failed to create the user. Please try again.', self::ERROR_CREATE_FAILED);
        }

        return Result::success(data: $userResponseDTO, message: 'User created successfully.');
    }

    /**
     * Updates a user inside a transaction, holding a row lock from the read
     * through to the save.
     *
     * The account's status is not part of this payload: it moves only through
     * the entity's own approval and suspension rules.
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

            $existingUser = $this->userRepository->findByIdForUpdate($userUpdateDTO->id);

            if ($existingUser === null) {
                $this->transactionManager->rollback();

                return Result::failure('User not found.', self::ERROR_NOT_FOUND);
            }

            $oldImagePath = $existingUser->image;

            if ($userUpdateDTO->image !== null) {
                $newImagePath = $this->fileUploader->upload(
                    file: $userUpdateDTO->image,
                    directory: self::IMAGE_DIRECTORY,
                );
            }

            // An omitted password keeps the stored hash as it stands.
            $password = $userUpdateDTO->password !== null && $userUpdateDTO->password !== ''
                ? $this->passwordHasher->hash($userUpdateDTO->password)
                : $existingUser->password;

            $updatedUser = $existingUser->applyChanges(
                name: $userUpdateDTO->name,
                email: $userUpdateDTO->email,
                password: $password,
                image: $newImagePath ?? $oldImagePath,
                role: $userUpdateDTO->role,
            );

            $savedUser = $this->userRepository->update($updatedUser);
            $userResponseDTO = $this->responseMapper->toResponseDTO($savedUser);
            $this->transactionManager->commit();
        } catch (InvalidUserException $exception) {
            $this->transactionManager->rollback();
            $this->removeUploadedImage($newImagePath);

            return Result::failure($exception->getMessage(), self::ERROR_INVALID);
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();
            $this->removeUploadedImage($newImagePath);
            $this->logFailure('User update failed.', $exception, ['id' => $userUpdateDTO->id]);

            return Result::failure('Failed to update the user. Please try again.', self::ERROR_UPDATE_FAILED);
        }

        // The replaced image is dropped only once the new one is committed, so a
        // failed write never leaves the row pointing at a deleted file.
        if ($newImagePath !== null && $oldImagePath !== null && $newImagePath !== $oldImagePath) {
            $this->fileUploader->delete($oldImagePath);
        }

        return Result::success(data: $userResponseDTO, message: 'User updated successfully.');
    }

    /**
     * Moves a user's account status, letting the entity refuse a move it does
     * not allow.
     *
     * @param int        $id     The user ID.
     * @param AuthStatus $status The status an administrator picked.
     *
     * @return Result Contains the UserResponseDTO on success.
     */
    public function updateUserStatus(int $id, AuthStatus $status): Result
    {
        try {
            $this->transactionManager->beginTransaction();

            $existingUser = $this->userRepository->findByIdForUpdate($id);

            if ($existingUser === null) {
                $this->transactionManager->rollback();

                return Result::failure('User not found.', self::ERROR_NOT_FOUND);
            }

            $statusChangedUser = $existingUser->changeStatusTo($status);
            $savedUser = $this->userRepository->updateStatus($statusChangedUser);
            $userResponseDTO = $this->responseMapper->toResponseDTO($savedUser);
            $this->transactionManager->commit();
        } catch (UserAlreadyApprovedException $exception) {
            $this->transactionManager->rollback();

            return Result::failure($exception->getMessage(), self::ERROR_ALREADY_APPROVED);
        } catch (UserAlreadyActiveException $exception) {
            $this->transactionManager->rollback();

            return Result::failure($exception->getMessage(), self::ERROR_ALREADY_ACTIVE);
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();
            $this->logFailure('User status update failed.', $exception, ['id' => $id]);

            return Result::failure('Failed to update the user status. Please try again.', self::ERROR_STATUS_UPDATE_FAILED);
        }

        return Result::success(data: $userResponseDTO, message: 'User status updated successfully.');
    }

    /**
     * Deletes a user and removes their profile image afterwards.
     *
     * @param int $id The user ID.
     *
     * @return Result Contains no data on success.
     */
    public function deleteUser(int $id): Result
    {
        $imagePath = null;

        try {
            $this->transactionManager->beginTransaction();

            $userEntity = $this->userRepository->findById($id);

            if ($userEntity === null) {
                $this->transactionManager->rollback();

                return Result::failure('User not found.', self::ERROR_NOT_FOUND);
            }

            $imagePath = $userEntity->image;

            if (! $this->userRepository->delete($id)) {
                $this->transactionManager->rollback();

                return Result::failure('Failed to delete the user. It might be in use.', self::ERROR_DELETE_FAILED);
            }

            $this->transactionManager->commit();
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();
            $this->logFailure('User deletion failed.', $exception, ['id' => $id]);

            return Result::failure("We couldn't delete the user right now. Please try again.", self::ERROR_DELETE_FAILED);
        }

        // The image is dropped only once the row is gone, so a failed delete
        // never leaves a user pointing at a file that no longer exists.
        $this->removeUploadedImage($imagePath);

        return Result::success(data: null, message: 'User deleted successfully.');
    }

    /**
     * Verifies credentials and returns the account. The account's own status
     * decides whether it may sign in.
     *
     * The generic credential message avoids disclosing whether the email or the
     * password was wrong.
     *
     * @param UserLoginDTO $userLoginDTO
     *
     * @return Result Contains the UserResponseDTO on success.
     */
    public function login(UserLoginDTO $userLoginDTO): Result
    {
        try {
            $userEntity = $this->userRepository->findByEmail($userLoginDTO->email);

            if ($userEntity === null) {
                return Result::failure(
                    'The provided credentials do not match our records.',
                    self::ERROR_INVALID_CREDENTIALS,
                );
            }

            $passwordMatches = $userEntity->passwordMatches(
                $userLoginDTO->password,
                fn(string $plainPassword, string $storedHash) => $this->passwordHasher->verify(
                    plainPassword: $plainPassword,
                    hashedPassword: $storedHash,
                ),
            );

            if (! $passwordMatches) {
                return Result::failure(
                    'The provided credentials do not match our records.',
                    self::ERROR_INVALID_CREDENTIALS,
                );
            }

            // The entity refuses the sign-in itself; the service only maps the
            // refusal onto the error key its delivery adapters already use.
            $userEntity->assertCanSignIn();
        } catch (UserCannotAuthenticateException $exception) {
            return Result::failure($exception->getMessage(), $this->signInErrorCode($exception));
        } catch (\Throwable $exception) {
            $this->logFailure('Login attempt failed.', $exception, []);

            return Result::failure("We couldn't log you in right now. Please try again.", self::ERROR_LOGIN_FAILED);
        }

        return Result::success(
            data: $this->responseMapper->toResponseDTO($userEntity),
            message: 'Logged in successfully.',
        );
    }

    /**
     * Maps a refused sign-in onto the error key the delivery adapters branch on.
     *
     * @param UserCannotAuthenticateException $exception
     *
     * @return string
     */
    private function signInErrorCode(UserCannotAuthenticateException $exception): string
    {
        if (str_contains($exception->getMessage(), 'awaiting approval')) {
            return self::ERROR_PENDING;
        }

        if (str_contains($exception->getMessage(), 'suspended')) {
            return self::ERROR_SUSPENDED;
        }

        return self::ERROR_LOGIN_FAILED;
    }

    /**
     * Removes an uploaded image, never letting a cleanup failure mask the
     * original outcome.
     *
     * @param string|null $imagePath
     *
     * @return void
     */
    private function removeUploadedImage(?string $imagePath): void
    {
        if ($imagePath === null || $imagePath === '') {
            return;
        }

        try {
            $this->fileUploader->delete($imagePath);
        } catch (\Throwable $exception) {
            $this->logFailure('Removing a user image failed.', $exception, ['image' => $imagePath]);
        }
    }

    /**
     * @param string     $message
     * @param \Throwable $exception
     * @param array      $context
     *
     * @return void
     */
    private function logFailure(string $message, \Throwable $exception, array $context): void
    {
        $this->logger->error($message, [
            ...$context,
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
