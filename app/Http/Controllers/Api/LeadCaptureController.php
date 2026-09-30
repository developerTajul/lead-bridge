<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeadCaptureRequest;
use App\Jobs\ProcessInboundLead;
use Illuminate\Http\JsonResponse;

class LeadCaptureController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(LeadCaptureRequest $request): JsonResponse
    {
        // ব্যাকগ্রাউন্ড কিউতে পাঠিয়ে দেওয়া
        ProcessInboundLead::dispatch($request->validated());

        return response()->json([
            'status'  => 'accepted',
            'message' => 'Lead received and queued for ingestion.',
        ], 202);
    }
}