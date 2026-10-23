<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexDealRequest;
use App\Http\Requests\Api\V1\StoreDealRequest;
use App\Http\Requests\Api\V1\UpdateDealRequest;
use Core\Deal\Application\Contracts\DealMapperContract;
use Core\Deal\Application\Services\DealQueryService;
use Core\Deal\Application\Services\DealService;
use Core\Deal\Domain\Contracts\DealRepositoryContract;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class DealController extends Controller
{
    private const ERROR_INVALID = 'DEAL_INVALID';

    private const ERROR_NOT_FOUND = 'DEAL_NOT_FOUND';

    /**
     * @param DealService            $dealService
     * @param DealQueryService       $dealQueryService
     * @param DealMapperContract     $mapper
     * @param DealRepositoryContract $dealRepository
     */
    public function __construct(
        private readonly DealService $dealService,
        private readonly DealQueryService $dealQueryService,
        private readonly DealMapperContract $mapper,
        private readonly DealRepositoryContract $dealRepository,
    ) {}

    /**
     * Display a listing of deals with relations.
     *
     * @param IndexDealRequest $request
     *
     * @return JsonResponse
     */
    public function index(IndexDealRequest $request): JsonResponse
    {
        // An API client sends the filters it means, so an unusable one is a
        // client error rather than something to silently drop.
        try {
            $dealFilterDTO = $this->mapper->mapToFilterDTO($request->query());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'error_code' => self::ERROR_INVALID,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->dealQueryService->getPaginatedDeals($dealFilterDTO);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
                'error_code' => $result->errorCode,
            ], $this->errorStatus($result->errorCode));
        }

        $paginatedDeals = $result->getData();

        return response()->json([
            'status'  => 'success',
            'message' => $result->getMessage(),
            'data'    => $paginatedDeals->items,
            'meta'    => [
                'total' => $paginatedDeals->total,
                'current_page' => $paginatedDeals->currentPage,
                'per_page' => $paginatedDeals->perPage,
                'last_page' => $paginatedDeals->lastPage,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created deal.
     *
     * @param StoreDealRequest $request
     *
     * @return JsonResponse
     */
    public function store(StoreDealRequest $request): JsonResponse
    {
        // A client sends the deal it means, so an unusable value is a client
        // error rather than something to silently drop.
        try {
            $storeDealDTO = $this->mapper->mapToStoreDTO($request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'error_code' => self::ERROR_INVALID,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->dealService->createDeal($storeDealDTO);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
                'error_code' => $result->errorCode,
            ], $this->errorStatus($result->errorCode));
        }

        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => $result->getData(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Maps a domain error code to the HTTP status the client should see.
     * A broken deal invariant is a validation failure, not a bad request.
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

        return Response::HTTP_BAD_REQUEST;
    }

    /**
     * Display the specified deal.
     *
     * @param string $id
     *
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $result = $this->dealQueryService->getDealDetails($id);

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
     * Update the specified deal.
     *
     * The stage's name is read before the mapper runs, because whether the new
     * stage closes the deal is stated in names and only storage knows them.
     *
     * @param UpdateDealRequest $request
     * @param string            $id
     *
     * @return JsonResponse
     */
    public function update(UpdateDealRequest $request, string $id): JsonResponse
    {
        try {
            $updateDealDTO = $this->mapper->mapToUpdateDTO(
                id: (int) $id,
                validatedData: $request->validated(),
                stageName: $this->stageNameFor($request->validated()),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'error_code' => self::ERROR_INVALID,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->dealService->updateDeal($updateDealDTO);

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
     * The name of the stage a payload moves the deal to, or null when the
     * payload names no stage.
     *
     * @param array $validatedData
     *
     * @return string|null
     */
    private function stageNameFor(array $validatedData): ?string
    {
        if (! isset($validatedData['deal_stage_id'])) {
            return null;
        }

        return $this->dealRepository->findStageNameById((int) $validatedData['deal_stage_id']);
    }

    /**
     * Remove the specified deal.
     *
     * @param string $id
     *
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $result = $this->dealService->deleteDeal((int) $id);

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
        ], Response::HTTP_OK);
    }
}
