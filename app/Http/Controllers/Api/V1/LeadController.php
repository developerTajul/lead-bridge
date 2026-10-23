<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\LeadStoreRequest;
use App\Http\Requests\Lead\UpdateLeadStatusRequest;
use Core\Lead\Application\Contracts\LeadMapperContract;
use Core\Lead\Application\Contracts\LeadServiceInterface;
use Core\Lead\Domain\Enums\LeadStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * JSON endpoints for the lead resource.
 */
final class LeadController extends Controller
{
    private const ERROR_INVALID = 'LEAD_INVALID';

    public function __construct(
        private readonly LeadServiceInterface $leadService,
        private readonly LeadMapperContract $mapper
    ) {}

    /**
     * Lists the leads matching the given filters.
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // An API client sends the filters it means, so an unusable one is a
        // client error rather than something to silently drop.
        try {
            $leadFilterDTO = $this->mapper->mapToFilterDTO($request->query());
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), self::ERROR_INVALID, self::ERROR_INVALID);
        }

        $result = $this->leadService->getLeads($leadFilterDTO);

        if ($result->isFailure()) {
            return $this->errorResponse($result->getMessage(), $result->errorCode, self::ERROR_INVALID);
        }

        $paginatedLeads = $result->getData();

        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => $paginatedLeads->items,
            'meta' => [
                'total' => $paginatedLeads->total,
                'current_page' => $paginatedLeads->currentPage,
                'per_page' => $paginatedLeads->perPage,
                'last_page' => $paginatedLeads->lastPage,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Shows a single lead.
     *
     * @param int $id
     *
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->leadService->getLeadById($id);

        if ($result->isFailure()) {
            return $this->errorResponse($result->getMessage(), $result->errorCode, 'LEAD_NOT_FOUND');
        }

        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => $result->getData(),
        ], Response::HTTP_OK);
    }

    /**
     * Stores a new lead.
     *
     * @param LeadStoreRequest $request
     *
     * @return JsonResponse
     */
    public function store(LeadStoreRequest $request): JsonResponse
    {
        $leadCreateDTO = $this->mapper->mapToCreateDTO($request->validated());

        $result = $this->leadService->createLead($leadCreateDTO);

        if ($result->isFailure()) {
            return $this->errorResponse($result->getMessage(), $result->errorCode, 'LEAD_CREATE_FAILED');
        }

        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => $result->getData(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Changes a lead's status through the domain rules.
     *
     * @param UpdateLeadStatusRequest $request
     * @param int                     $lead
     *
     * @return JsonResponse
     */
    public function updateStatus(UpdateLeadStatusRequest $request, int $lead): JsonResponse
    {
        $status = LeadStatus::from($request->validated('status'));

        $result = $this->leadService->changeStatus($lead, $status);

        if ($result->isFailure()) {
            return $this->errorResponse($result->getMessage(), $result->errorCode, self::ERROR_INVALID);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => $result->getData(),
        ], Response::HTTP_OK);
    }

    /**
     * Maps a domain error code to the HTTP status the client should see.
     *
     * @param string      $message
     * @param string|null $errorCode
     * @param string      $fallbackCode
     *
     * @return JsonResponse
     */
    private function errorResponse(string $message, ?string $errorCode, string $fallbackCode): JsonResponse
    {
        $status = match ($errorCode) {
            // A rejected filter is client input, so it is a validation failure
            // rather than a bare bad request.
            self::ERROR_INVALID => Response::HTTP_UNPROCESSABLE_ENTITY,
            // A duplicate lead is a conflict with existing state.
            'LEAD_ALREADY_EXISTS' => Response::HTTP_CONFLICT,
            'LEAD_NOT_FOUND' => Response::HTTP_NOT_FOUND,
            default => Response::HTTP_BAD_REQUEST,
        };

        return response()->json([
            'status' => 'error',
            'message' => $message,
            'error_code' => $errorCode ?? $fallbackCode,
        ], $status);
    }
}
