<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\IndexCompanyRequest;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use Core\Company\Application\Contracts\CompanyMapperContract;
use Core\Company\Application\Services\CompanyQueryService;
use Core\Company\Application\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class CompanyController extends Controller
{
    private const ERROR_INVALID = 'COMPANY_INVALID';

    private const ERROR_NOT_FOUND = 'COMPANY_NOT_FOUND';

    /**
     * @param CompanyService        $companyService
     * @param CompanyQueryService   $companyQueryService
     * @param CompanyMapperContract $mapper
     */
    public function __construct(
        private readonly CompanyService $companyService,
        private readonly CompanyQueryService $companyQueryService,
        private readonly CompanyMapperContract $mapper,
    ) {}

    /**
     * Lists the companies matching the given filters.
     *
     * @param IndexCompanyRequest $request
     *
     * @return JsonResponse
     */
    public function index(IndexCompanyRequest $request): JsonResponse
    {
        // An API client sends the filters it means, so an unusable one is a
        // client error rather than something to silently drop.
        try {
            $companyFilterDTO = $this->mapper->mapToFilterDTO($request->query());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'error_code' => self::ERROR_INVALID,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->companyQueryService->getPaginatedCompanies($companyFilterDTO);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
                'error_code' => $result->errorCode,
            ], $this->errorStatus($result->errorCode));
        }

        $paginatedCompanies = $result->getData();

        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => $paginatedCompanies->items,
            'meta' => [
                'total' => $paginatedCompanies->total,
                'current_page' => $paginatedCompanies->currentPage,
                'per_page' => $paginatedCompanies->perPage,
                'last_page' => $paginatedCompanies->lastPage,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created company.
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $storeCompanyDTO = $this->mapper->mapToStoreDTO($request->validated());

        $result = $this->companyService->createCompany($storeCompanyDTO);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->message,
                'error_code' => $result->errorCode,
            ], $this->errorStatus($result->errorCode));
        }

        return response()->json([
            'status' => 'success',
            'message' => $result->message,
            'data' => $result->data,
        ], Response::HTTP_CREATED);
    }

    /**
     * Maps a domain error code to the HTTP status the client should see.
     * A broken company invariant is a validation failure, not a bad request.
     *
     * @param string|null $errorCode
     *
     * @return int
     */
    private function errorStatus(?string $errorCode): int
    {
        if ($errorCode === self::ERROR_INVALID) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return Response::HTTP_BAD_REQUEST;
    }


    /**
     * Shows the specified company with its 360-degree relationship data.
     *
     * @param string $id
     *
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $result = $this->companyQueryService->getCompanyDetails($id);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->message,
                'error_code' => $result->errorCode,
            ], $result->errorCode === self::ERROR_NOT_FOUND
                ? Response::HTTP_NOT_FOUND
                : Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result->message,
            'data' => $result->getData(),
        ], Response::HTTP_OK);
    }

    /**
     * Updates the specified company.
     *
     * @param UpdateCompanyRequest $request
     * @param string               $id
     *
     * @return JsonResponse
     */
    public function update(UpdateCompanyRequest $request, string $id): JsonResponse
    {
        $updateCompanyDTO = $this->mapper->mapToUpdateDTO((int) $id, $request->validated());

        $result = $this->companyService->updateCompany($updateCompanyDTO);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->message,
                'error_code' => $result->errorCode,
            ], $result->errorCode === self::ERROR_NOT_FOUND
                ? Response::HTTP_NOT_FOUND
                : $this->errorStatus($result->errorCode));
        }

        return response()->json([
            'status' => 'success',
            'message' => $result->message,
            'data' => $result->getData(),
        ], Response::HTTP_OK);
    }

    /**
     * Removes the specified company.
     *
     * @param string $id
     *
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $result = $this->companyService->deleteCompany($id);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->message,
                'error_code' => $result->errorCode,
            ], $result->errorCode === self::ERROR_NOT_FOUND
                ? Response::HTTP_NOT_FOUND
                : $this->errorStatus($result->errorCode));
        }

        return response()->json([
            'status' => 'success',
            'message' => $result->message,
        ], Response::HTTP_OK);
    }
}