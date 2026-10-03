<?php

declare(strict_types=1);

namespace Core\Auth\Application\Services;

use Core\Auth\Application\DTOs\Requests\LoginRequestDTO;
use Core\Auth\Application\DTOs\Requests\RegisterRequestDTO;
use Core\Auth\Application\Mappers\AuthResponseMapper;
use Core\Auth\Domain\Contracts\AuthRepositoryContract;
use Core\Auth\Domain\DataObjects\AuthRegisterData;
use Core\Auth\Domain\Enums\AuthStatus;
use Core\Shared\Application\DTOs\Result;
use Core\Shared\Domain\Contracts\PasswordHasherContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Psr\Log\LoggerInterface;

/**
 * Handles user registration and login. Returns Result objects for success/failure.
 */
final class AuthService
{

    public function __construct(
        private readonly AuthRepositoryContract $authRepository,
        private readonly AuthResponseMapper $mapper,
        private readonly TransactionManagerContract $transactionManager,
        private readonly LoggerInterface $logger,
        private readonly PasswordHasherContract $passwordHasher,
    ) {}

    /**
     * Registers a new user inside a transaction.
     *
     * @param RegisterRequestDTO $userRegisterDTO
     *
     * @return Result Contains the AuthResponseDTO on success.
     */
    public function register(RegisterRequestDTO $userRegisterDTO): Result
    {

        try {
            $this->transactionManager->beginTransaction();

            $userData = new AuthRegisterData(
                name: $userRegisterDTO->name,
                email: $userRegisterDTO->email,
                password: $this->passwordHasher->hash($userRegisterDTO->password),
                role: $userRegisterDTO->role,
                status: $userRegisterDTO->status,
            );

            $userEntity = $this->authRepository->save($userData);
            
            $this->transactionManager->commit();
            
            $responseDTO = $this->mapper->toResponseDTO($userEntity);

            return Result::success(
                data: $responseDTO,
                message: 'Registration successful! You can now log in.',
            );
        } catch (\Throwable $exception) {
            $this->transactionManager->rollback();

            $this->logger->error('User registration failed.', ['exception' => $exception]);

            return Result::failure(
                message: 'Registration failed. Please try again.',
                errorCode: 'AUTH_REGISTRATION_FAILED',
            );
        }
    }

    /**
     * Verifies credentials and returns the authenticated user id.
     * The delivery layer owns session creation.
     *
     * @param LoginRequestDTO $userLoginDTO
     *
     * @return Result Contains the authenticated user id on success.
     */
    public function login(LoginRequestDTO $userLoginDTO): Result
    {

        try {
            $user = $this->authRepository->findByEmail($userLoginDTO->email);   
            if ($user === null) {
                return Result::failure(
                    message: 'Invalid email or password.',
                    errorCode: 'AUTH_INVALID_CREDENTIALS',
                );
            }

            $passwordMatches = $this->passwordHasher->verify(
                plainPassword: $userLoginDTO->password,
                hashedPassword: $user->password,
            );

            if (!$passwordMatches) {
                return Result::failure(
                    message: 'Invalid email or password.',
                    errorCode: 'AUTH_INVALID_CREDENTIALS',
                );
            }

            if ($user->status !== AuthStatus::APPROVED) {
                return Result::failure(
                    message: 'Your account is not active. Please contact the administrator.',
                    errorCode: 'AUTH_ACCOUNT_INACTIVE',
                );
            }

            $responseDTO = $this->mapper->toResponseDTO($user);

            return Result::success(data: $responseDTO, message: 'Login successful!');

        } catch (\Throwable $exception) {
            $this->logger->error('User login failed.', ['exception' => $exception]);

            return Result::failure(
                message: 'We couldn\'t log you in right now. Please try again.',
                errorCode: 'AUTH_LOGIN_FAILED',
            );
        }
    }



    public function getProfile(int $userId): Result
    {

        try {
            $user = $this->authRepository->findById($userId);

            if ($user === null) {
                return Result::failure(
                    message: 'User not found.',
                    errorCode: 'AUTH_USER_NOT_FOUND',
                );
            }

            $responseDTO = $this->mapper->toResponseDTO($user);

            return Result::success(data: $responseDTO, message: 'Profile fetched successfully.');

        } catch (\Throwable $exception) {
            $this->logger->error('User profile fetch failed.', ['exception' => $exception]);

            return Result::failure(
                message: 'We couldn\'t fetch your profile right now. Please try again.',
                errorCode: 'AUTH_PROFILE_FETCH_FAILED',
            );
        }
    }




}
