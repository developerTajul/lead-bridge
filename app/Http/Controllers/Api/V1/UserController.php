<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use Core\User\Application\Contracts\UserMapperContract;
use Core\User\Application\Mappers\UserListFilterMapper;
use Core\User\Application\Services\UserQueryService;
use Core\User\Application\Services\UserService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class UserController extends Controller
{
    private const ERROR_INVALID = 'USER_INVALID';

    private const ERROR_NOT_FOUND = 'USER_NOT_FOUND';

    /**
     * @param UserService           $userService
     * @param UserQueryService      $userQueryService
     * @param UserListFilterMapper  $listFilterMapper
     * @param UserMapperContract    $mapper
     */
    public function __construct(
        private readonly UserService $userService,
        private readonly UserQueryService $userQueryService,
        private readonly UserListFilterMapper $listFilterMapper,
        private readonly UserMapperContract $mapper,
    ) {}

    /**
     * Display a listing of users.
     *
     * @param IndexUserRequest $request
     *
     * @return JsonResponse
     */
    public function index(IndexUserRequest $request): JsonResponse
    {
        // An API client sends the filters it means, so an unusable one is a
        // client error rather than something to silently drop.
        try {
            $userFilterDTO = $this->listFilterMapper->mapToFilterDTO($request->query());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'error_code' => self::ERROR_INVALID,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->userQueryService->getPaginatedUsers($userFilterDTO);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
                'error_code' => $result->errorCode,
            ], $this->errorStatus($result->errorCode));
        }

        $paginatedUsers = $result->getData();

        return response()->json([
            'status'  => 'success',
            'message' => $result->getMessage(),
            'data'    => $paginatedUsers->items,
            'meta'    => [
                'total' => $paginatedUsers->total,
                'current_page' => $paginatedUsers->currentPage,
                'per_page' => $paginatedUsers->perPage,
                'last_page' => $paginatedUsers->lastPage,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Display the specified user.
     *
     * @param string $id
     *
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $result = $this->userQueryService->getUserDetails($id);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
                'error_code' => $result->errorCode,
            ], $this->errorStatus($result->errorCode));
        }

        return response()->json([
            'status'  => 'success',
            'message' => $result->getMessage(),
            'data'    => $result->getData(),
        ], Response::HTTP_OK);
    }

    /**
     * Updates the specified user.
     *
     * @param UpdateUserRequest $request
     * @param string            $id
     *
     * @return JsonResponse
     */
    public function update(UpdateUserRequest $request, string $id): JsonResponse
    {
        $userUpdateDTO = $this->mapper->mapToUpdateDTO((int) $id, $request->validated());

        $result = $this->userService->updateUser($userUpdateDTO);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
                'error_code' => $result->errorCode,
            ], $this->errorStatus($result->errorCode));
        }

        return response()->json([
            'status'  => 'success',
            'message' => $result->getMessage(),
            'data'    => $result->getData(),
        ], Response::HTTP_OK);
    }

    /**
     * Maps a domain error key to the status the client sees.
     *
     * @param string|null $errorCode
     *
     * @return int
     */
    private function errorStatus(?string $errorCode): int
    {
        if ($errorCode === self::ERROR_NOT_FOUND) {
            return Response::HTTP_NOT_FOUND;
        }

        if ($errorCode === self::ERROR_INVALID) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
