<?php

declare(strict_types=1);

namespace Core\User\Application\Services;

use Core\Shared\Application\DTOs\Result;
use Core\User\Application\DTOs\Requests\UserFilterDTO;
use Core\User\Application\Mappers\UserListResponseMapper;
use Core\User\Domain\Contracts\UserQueryContract;
use Core\User\Domain\DataObjects\StoredUserData;
use Core\User\Domain\Exceptions\InvalidUserException;
use Core\User\Domain\ValueObjects\UserFilter;
use Psr\Log\LoggerInterface;

/**
 * Handles user read use cases.
 */
final class UserQueryService
{
    private const ERROR_INVALID = 'USER_INVALID';

    private const ERROR_INDEX_FAILED = 'USER_INDEX_FAILED';

    private const ERROR_NOT_FOUND = 'USER_NOT_FOUND';

    private const ERROR_DETAIL_FAILED = 'USER_DETAIL_FAILED';

    /**
     * @param UserQueryContract       $userQuery
     * @param UserListResponseMapper  $responseMapper
     * @param LoggerInterface         $logger
     */
    public function __construct(
        private readonly UserQueryContract $userQuery,
        private readonly UserListResponseMapper $responseMapper,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Returns the paginated user list matching the given criteria.
     *
     * @param UserFilterDTO $userFilterDTO
     *
     * @return Result Contains the PaginatedResult of UserListItemResponseDTO on success.
     */
    public function getPaginatedUsers(UserFilterDTO $userFilterDTO): Result
    {
        try {
            $userFilter = new UserFilter(perPage: $userFilterDTO->perPage);

            $paginatedUsers = $this->userQuery->getFilteredUsers($userFilter);

            // Read models become response DTOs here, so nothing downstream of
            // the Service ever sees a domain object.
            $mappedPaginatedResult = $paginatedUsers->map(
                fn(StoredUserData $storedUser) => $this->responseMapper->toResponseDTO($storedUser)
            );

            return Result::success(
                data: $mappedPaginatedResult,
                message: 'Users retrieved successfully.',
            );
        } catch (InvalidUserException $exception) {
            // A rejected filter is bad input, not a system failure.
            $this->logger->warning('User list rejected an invalid filter.', [
                'message' => $exception->getMessage(),
            ]);

            return Result::failure($exception->getMessage(), self::ERROR_INVALID);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve users.', ['exception' => $exception]);

            return Result::failure('Failed to retrieve users.', self::ERROR_INDEX_FAILED);
        }
    }

    /**
     * Returns one user by id.
     *
     * @param string $id
     *
     * @return Result Contains the UserListItemResponseDTO on success.
     */
    public function getUserDetails(string $id): Result
    {
        try {
            $storedUser = $this->userQuery->findUserById((int) $id);

            // A missing user is an ordinary outcome of a read, so it is
            // reported rather than thrown.
            if ($storedUser === null) {
                return Result::failure('User not found.', self::ERROR_NOT_FOUND);
            }

            return Result::success(
                data: $this->responseMapper->toResponseDTO($storedUser),
                message: 'User retrieved successfully.',
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to retrieve user.', ['exception' => $exception]);

            return Result::failure('Failed to retrieve user.', self::ERROR_DETAIL_FAILED);
        }
    }
}
