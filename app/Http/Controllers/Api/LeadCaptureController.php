<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\LeadCaptureRequest;
use Core\Lead\Application\Contracts\LeadMapperContract;
use Core\Lead\Application\Contracts\LeadServiceInterface;
use Illuminate\Http\JsonResponse;

/**
 * Handles lead capture requests.
 */
final class LeadCaptureController extends Controller
{
    public function __construct(
        private readonly LeadMapperContract $mapper,
        private readonly LeadServiceInterface $leadService,
    ) {}

    public function __invoke(LeadCaptureRequest $request): JsonResponse
    {
        $dto = $this->mapper->mapToCreateDTO($request->validated());
        
        $this->leadService->captureLead($dto);

        return response()->json([
            'status'  => 'accepted',
            'message' => 'Lead received and queued for ingestion.',
        ], 202);
    }
}
