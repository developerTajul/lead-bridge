<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Core\Activity\Application\Services\ActivityService;
use Core\Lead\Application\Contracts\LeadServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the activity timeline of a lead.
 */
final class LeadActivityController extends Controller
{
    /**
     * The morph alias activities are stored under for a lead. Taken from the
     * Lead module's own identity rather than a literal, so the value written
     * by the Activity module and the value filtered on here cannot drift.
     */
    private const TRACKABLE_TYPE = 'lead';

    private const DEFAULT_PER_PAGE = 15;

    private const ERROR_INVALID = 'ACTIVITY_INVALID';

    private const ERROR_NOT_FOUND = 'LEAD_NOT_FOUND';

    public function __construct(
        private readonly LeadServiceInterface $leadService,
        private readonly ActivityService $activityService
    ) {}

    /**
     * Lists the activities attached to a lead.
     *
     * @param Request $request
     * @param int     $lead
     *
     * @return JsonResponse
     */
    public function index(Request $request, int $lead): JsonResponse
    {
        // The timeline hangs off the lead, so a lead that does not exist is a
        // missing resource rather than an empty timeline.
        $leadResult = $this->leadService->getLeadById($lead);

        if ($leadResult->isFailure()) {
            return $this->errorResponse('Lead not found.', self::ERROR_NOT_FOUND);
        }

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $this->activityService->getActivitiesForTrackable(
            trackableType: self::TRACKABLE_TYPE,
            trackableId: $lead,
            perPage: (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE),
        );

        if ($result->isFailure()) {
            return $this->errorResponse($result->getMessage(), $result->errorCode);
        }

        $paginatedActivities = $result->getData();

        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => $paginatedActivities->items,
            'meta' => [
                'total' => $paginatedActivities->total,
                'current_page' => $paginatedActivities->currentPage,
                'per_page' => $paginatedActivities->perPage,
                'last_page' => $paginatedActivities->lastPage,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Maps a domain error code to the HTTP status the client should see.
     *
     * @param string      $message
     * @param string|null $errorCode
     *
     * @return JsonResponse
     */
    private function errorResponse(string $message, ?string $errorCode): JsonResponse
    {
        $status = match ($errorCode) {
            // A rejected filter is client input, so it is a validation failure.
            self::ERROR_INVALID => Response::HTTP_UNPROCESSABLE_ENTITY,
            self::ERROR_NOT_FOUND => Response::HTTP_NOT_FOUND,
            default => Response::HTTP_BAD_REQUEST,
        };

        return response()->json([
            'status' => 'error',
            'message' => $message,
            'error_code' => $errorCode,
        ], $status);
    }
}
