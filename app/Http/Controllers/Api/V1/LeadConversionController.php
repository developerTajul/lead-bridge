<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Core\Lead\Application\Services\LeadConversionService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Converts a qualified lead into a customer.
 */
final class LeadConversionController extends Controller
{
    private const ERROR_INVALID = 'LEAD_INVALID';

    private const ERROR_NOT_FOUND = 'LEAD_NOT_FOUND';

    public function __construct(
        private readonly LeadConversionService $leadConversionService,
    ) {}

    /**
     * Converts the lead and hands the converted lead back.
     *
     * @param int $lead
     *
     * @return JsonResponse
     */
    public function convert(int $lead): JsonResponse
    {
        $result = $this->leadConversionService->convert($lead);

        if ($result->isFailure()) {
            return $this->errorResponse($result->getMessage(), $result->errorCode);
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
     *
     * @return JsonResponse
     */
    private function errorResponse(string $message, ?string $errorCode): JsonResponse
    {
        $status = match ($errorCode) {
            // A lead that may not convert is a state problem, so it is a
            // validation failure rather than a bad request.
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
