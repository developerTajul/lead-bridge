<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class DealController extends Controller
{
    /**
     * Display a listing of deals with relations.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Deal::with(['company', 'contact', 'pipeline', 'stage']);

        if ($request->filled('pipeline_id')) {
            $query->where('pipeline_id', $request->integer('pipeline_id'));
        }

        if ($request->filled('deal_stage_id')) {
            $query->where('deal_stage_id', $request->integer('deal_stage_id'));
        }

        $deals = $query->latest()->paginate(15);

        return response()->json([
            'status'  => 'success',
            'message' => 'Deals retrieved successfully.',
            'data'    => $deals,
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created deal.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'               => ['required', 'string', 'max:255'],
            'company_id'          => ['nullable', 'integer', 'exists:companies,id'],
            'contact_id'          => ['nullable', 'integer', 'exists:contacts,id'],
            'amount'              => ['nullable', 'numeric', 'min:0'],
            'currency'            => ['nullable', 'string', 'size:3'],
            'pipeline_id'         => ['required', 'integer', 'exists:pipelines,id'],
            'deal_stage_id'       => ['required', 'integer', 'exists:deal_stages,id'],
            'expected_close_date' => ['nullable', 'date'],
            'actual_close_date'   => ['nullable', 'date'],
        ]);

        $deal = DB::transaction(function () use ($validated): Deal {
            return Deal::create([
                'title'               => $validated['title'],
                'company_id'          => $validated['company_id'] ?? null,
                'contact_id'          => $validated['contact_id'] ?? null,
                'amount'              => $validated['amount'] ?? 0.00,
                'currency'            => $validated['currency'] ?? 'BDT',
                'pipeline_id'         => $validated['pipeline_id'],
                'deal_stage_id'       => $validated['deal_stage_id'],
                'expected_close_date' => $validated['expected_close_date'] ?? null,
                'actual_close_date'   => $validated['actual_close_date'] ?? null,
            ]);
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Deal created successfully.',
            'data'    => $deal->load(['company', 'contact', 'pipeline', 'stage']),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified deal.
     */
    public function show(Deal $deal): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => 'Deal retrieved successfully.',
            'data'    => $deal->load(['company', 'contact', 'pipeline', 'stage', 'activities']),
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified deal with row locking.
     */
    public function update(Request $request, Deal $deal): JsonResponse
    {
        $validated = $request->validate([
            'title'               => ['sometimes', 'required', 'string', 'max:255'],
            'company_id'          => ['nullable', 'integer', 'exists:companies,id'],
            'contact_id'          => ['nullable', 'integer', 'exists:contacts,id'],
            'amount'              => ['sometimes', 'numeric', 'min:0'],
            'currency'            => ['sometimes', 'string', 'size:3'],
            'pipeline_id'         => ['sometimes', 'required', 'integer', 'exists:pipelines,id'],
            'deal_stage_id'       => ['sometimes', 'required', 'integer', 'exists:deal_stages,id'],
            'expected_close_date' => ['nullable', 'date'],
            'actual_close_date'   => ['nullable', 'date'],
        ]);

        // Won (5) and Lost (6) stages close the deal, so stamp the close date.
        if (isset($validated['deal_stage_id']) && in_array((int) $validated['deal_stage_id'], [5, 6], true)) {
            $validated['actual_close_date'] = $validated['actual_close_date'] ?? now()->toDateString();
        }

        $updatedDeal = DB::transaction(function () use ($deal, $validated): Deal {
            $record = Deal::where('id', $deal->id)->lockForUpdate()->firstOrFail();
            $record->update($validated);

            return $record;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Deal updated successfully.',
            'data'    => $updatedDeal->fresh(['company', 'contact', 'pipeline', 'stage']),
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified deal.
     */
    public function destroy(Deal $deal): JsonResponse
    {
        $deal->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Deal deleted successfully.',
        ], Response::HTTP_OK);
    }
}