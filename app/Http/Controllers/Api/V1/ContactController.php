<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexContactRequest;
use App\Http\Requests\Api\V1\StoreContactRequest;
use App\Http\Requests\Api\V1\UpdateContactRequest;
use Core\Contact\Application\Contracts\ContactMapperContract;
use Core\Contact\Application\Services\ContactQueryService;
use Core\Contact\Application\Services\ContactService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class ContactController extends Controller
{
    private const ERROR_INVALID = 'CONTACT_INVALID';

    private const ERROR_NOT_FOUND = 'CONTACT_NOT_FOUND';

    /**
     * @param ContactService        $contactService
     * @param ContactQueryService   $contactQueryService
     * @param ContactMapperContract $mapper
     */
    public function __construct(
        private readonly ContactService $contactService,
        private readonly ContactQueryService $contactQueryService,
        private readonly ContactMapperContract $mapper,
    ) {}

    /**
     * Display a listing of contacts.
     *
     * @param IndexContactRequest $request
     *
     * @return JsonResponse
     */
    public function index(IndexContactRequest $request): JsonResponse
    {
        // An API client sends the filters it means, so an unusable one is a
        // client error rather than something to silently drop.
        try {
            $contactFilterDTO = $this->mapper->mapToFilterDTO($request->query());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'error_code' => self::ERROR_INVALID,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->contactQueryService->getPaginatedContacts($contactFilterDTO);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
                'error_code' => $result->errorCode,
            ], $this->errorStatus($result->errorCode));
        }

        $paginatedContacts = $result->getData();

        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => $paginatedContacts->items,
            'meta' => [
                'total' => $paginatedContacts->total,
                'current_page' => $paginatedContacts->currentPage,
                'per_page' => $paginatedContacts->perPage,
                'last_page' => $paginatedContacts->lastPage,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created contact (with optional company linkage).
     *
     * @param StoreContactRequest $request
     *
     * @return JsonResponse
     */
    public function store(StoreContactRequest $request): JsonResponse
    {
        $storeContactDTO = $this->mapper->mapToStoreDTO($request->validated());

        $result = $this->contactService->createContact($storeContactDTO);

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
            'data' => $result->getData(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Maps a domain error code to the HTTP status the client should see.
     * A broken contact invariant is a validation failure, not a bad request.
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
     * Display the specified contact.
     *
     * @param string $id
     *
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $result = $this->contactQueryService->getContactDetails($id);

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
     * Update the specified contact.
     *
     * @param UpdateContactRequest $request
     * @param string               $id
     *
     * @return JsonResponse
     */
    public function update(UpdateContactRequest $request, string $id): JsonResponse
    {
        $updateContactDTO = $this->mapper->mapToUpdateDTO((int) $id, $request->validated());

        $result = $this->contactService->updateContact($updateContactDTO);

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
     * Remove the specified contact.
     *
     * @param string $id
     *
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $result = $this->contactService->deleteContact((int) $id);

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