<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class PipelineController extends Controller
{
    /**
     * Display a listing of pipelines with their stages.
     */
    public function index(): JsonResponse
    {
        $pipelines = Pipeline::with('stages')->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Pipelines retrieved successfully.',
            'data'    => $pipelines,
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created pipeline along with its stages.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                     => ['required', 'string', 'max:255'],
            'is_default'               => ['nullable', 'boolean'],
            'stages'                   => ['required', 'array', 'min:1'],
            'stages.*.name'            => ['required', 'string', 'max:255'],
            'stages.*.order_position'  => ['required', 'integer', 'min:1'],
            'stages.*.probability'     => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $pipeline = DB::transaction(function () use ($validated): Pipeline {
            $newPipeline = Pipeline::create([
                'name'       => $validated['name'],
                'is_default' => $validated['is_default'] ?? false,
            ]);

            $newPipeline->stages()->createMany($validated['stages']);

            return $newPipeline;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Pipeline and stages created successfully.',
            'data'    => $pipeline->load('stages'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified pipeline.
     */
    public function show(Pipeline $pipeline): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => 'Pipeline retrieved successfully.',
            'data'    => $pipeline->load('stages'),
        ], Response::HTTP_OK);
    }

    /**
     * Display the pipeline with stages and nested deals for Kanban Board.
     */
    public function kanban(Pipeline $pipeline): JsonResponse
    {
        $pipeline->load([
            'stages' => function ($query) {
                $query->orderBy('order_position')
                    ->with([
                        'deals' => function ($dealQuery) {
                            $dealQuery->with(['company:id,name', 'contact:id,name'])
                                    ->latest();
                        }
                    ]);
            }
        ]);

        $board = $pipeline->stages->map(function ($stage) {
            return [
                'id'             => $stage->id,
                'name'           => $stage->name,
                'order_position' => $stage->order_position,
                'probability'    => $stage->probability,
                'deals_count'    => $stage->deals->count(),
                'total_value'    => (float) $stage->deals->sum('amount'),
                'deals'          => $stage->deals,
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Pipeline Kanban data retrieved successfully.',
            'data'    => [
                'pipeline' => [
                    'id'         => $pipeline->id,
                    'name'       => $pipeline->name,
                    'is_default' => $pipeline->is_default,
                ],
                'stages'   => $board,
            ],
        ], Response::HTTP_OK);
    }


}