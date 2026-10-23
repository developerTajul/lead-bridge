<?php

declare(strict_types=1);

namespace Tests\Unit\User;

use Core\Auth\Domain\Enums\AuthRole;
use Core\Auth\Domain\Enums\AuthStatus;
use Core\Shared\Application\DTOs\Result;
use Core\Shared\Domain\Contracts\FileUploaderContract;
use Core\Shared\Domain\Contracts\PasswordHasherContract;
use Core\Shared\Domain\Contracts\TransactionManagerContract;
use Core\User\Application\DTOs\Responses\UserResponseDTO;
use Core\User\Application\DTOs\Requests\UserLoginDTO;
use Core\User\Application\Mappers\UserResponseMapper;
use Core\User\Application\Services\UserService;
use Core\User\Domain\Contracts\UserRepositoryContract;
use Core\User\Domain\Entities\UserEntity;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the login use case.
 */
final class UserServiceTest extends TestCase
{
    /**
     * The entity refuses to be built from a plain-text password, so the fixture
     * carries a real bcrypt hash. The password hasher is mocked in these tests,
     * so the digest is never verified — it only has to be a genuine hash.
     */
    private const STORED_PASSWORD = '$2y$04$tw8QfuH3qzbVhlMOU7MhQe2XkPVxyWUYNh5lUgmPwVh6k33yqOm5S';

    private UserRepositoryContract $userRepository;

    private PasswordHasherContract $passwordHasher;

    private UserResponseMapper $responseMapper;

    private UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = $this->createMock(UserRepositoryContract::class);
        $this->passwordHasher = $this->createMock(PasswordHasherContract::class);

        $this->responseMapper = new UserResponseMapper();

        $this->userService = new UserService(
            userRepository: $this->userRepository,
            responseMapper: $this->responseMapper,
            transactionManager: $this->createMock(TransactionManagerContract::class),
            logger: $this->createMock(LoggerInterface::class),
            fileUploader: $this->createMock(FileUploaderContract::class),
            passwordHasher: $this->passwordHasher,
        );
    }

    public function test_login_succeeds_for_approved_user_with_valid_password(): void
    {
        $userEntity = $this->makeEntity(status: AuthStatus::APPROVED);

        $this->userRepository->method('findByEmail')->willReturn($userEntity);
        $this->passwordHasher->method('verify')->willReturn(true);

        $result = $this->userService->login(new UserLoginDTO(
            email: 'john@example.com',
            password: 'secret123',
        ));

        $this->assertTrue($result->isSuccess());
        $this->assertInstanceOf(UserResponseDTO::class, $result->data);
        $this->assertSame('john@example.com', $result->data->email);
    }

    public function test_login_fails_with_invalid_credentials_when_user_not_found(): void
    {
        $this->userRepository->method('findByEmail')->willReturn(null);

        $result = $this->userService->login(new UserLoginDTO(
            email: 'ghost@example.com',
            password: 'secret123',
        ));

        $this->assertTrue($result->isFailure());
        $this->assertSame('AUTH_INVALID_CREDENTIALS', $result->errorCode);
    }

    public function test_login_fails_with_invalid_credentials_on_wrong_password(): void
    {
        $userEntity = $this->makeEntity(status: AuthStatus::APPROVED);

        $this->userRepository->method('findByEmail')->willReturn($userEntity);
        $this->passwordHasher->method('verify')->willReturn(false);

        $result = $this->userService->login(new UserLoginDTO(
            email: 'john@example.com',
            password: 'wrong-password',
        ));

        $this->assertTrue($result->isFailure());
        $this->assertSame('AUTH_INVALID_CREDENTIALS', $result->errorCode);
    }

    public function test_login_fails_with_pending_error_for_pending_user(): void
    {
        $userEntity = $this->makeEntity(status: AuthStatus::PENDING);

        $this->userRepository->method('findByEmail')->willReturn($userEntity);
        $this->passwordHasher->method('verify')->willReturn(true);

        $result = $this->userService->login(new UserLoginDTO(
            email: 'john@example.com',
            password: 'secret123',
        ));

        $this->assertTrue($result->isFailure());
        $this->assertSame('AUTH_PENDING', $result->errorCode);
    }

    public function test_login_fails_with_suspended_error_for_suspended_user(): void
    {
        $userEntity = $this->makeEntity(status: AuthStatus::SUSPENDED);

        $this->userRepository->method('findByEmail')->willReturn($userEntity);
        $this->passwordHasher->method('verify')->willReturn(true);

        $result = $this->userService->login(new UserLoginDTO(
            email: 'john@example.com',
            password: 'secret123',
        ));

        $this->assertTrue($result->isFailure());
        $this->assertSame('AUTH_SUSPENDED', $result->errorCode);
    }

    private function makeEntity(AuthStatus $status): UserEntity
    {
        return new UserEntity(
            id: 1,
            name: 'John Doe',
            email: 'john@example.com',
            emailVerifiedAt: null,
            password: self::STORED_PASSWORD,
            image: null,
            role: AuthRole::MEMBER,
            status: $status,
            rememberToken: null,
            createdAt: '2026-09-30 10:00:00',
            updatedAt: '2026-09-30 10:00:00',
            deletedAt: null,
        );
    }
}