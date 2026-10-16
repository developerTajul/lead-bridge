<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\StoreActivityRequest;
use Core\Activity\Application\Contracts\ActivityMapperContract;
use Core\Activity\Application\DTOs\Requests\ActivityFilterDTO;
use Core\Activity\Application\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ActivityController extends Controller
{
    private const ERROR_INVALID = 'ACTIVITY_INVALID';

    public function __construct(
        private readonly ActivityService $activityService,
        private readonly ActivityMapperContract $mapper
    ){}

    /**
     * Display a listing of the resource with flexible filters.
     */
    public function index(Request $request): JsonResponse
    {
        $filterDTO = new ActivityFilterDTO(
            status: $request->input('status'),
            type: $request->input('type'),
            userId: $request->filled('user_id') ? $request->integer('user_id') : null,
            filter: $request->input('filter'),
            perPage: (int) $request->input('per_page', 15)
        );

        $result = $this->activityService->getActivities($filterDTO);

        if (! $result->success) {
            return response()->json([
                'status'  => 'error',
                'message' => $result->message,
                'error_code' => $result->errorCode ?? 'ACTIVITY_FETCH_FAILED'
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'status'  => 'success',
            'message' => $result->message ?? 'Activities retrieved successfully.',
            'data'    => $result->data,
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreActivityRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        $validatedData['user_id'] = auth()->id();

        $activityCreateDTO = $this->mapper->mapToCreateDTO($validatedData);

        $result = $this->activityService->create($activityCreateDTO);

        if (! $result->success) {
            return response()->json([
                'status'  => 'error',
                'message' => $result->message,
                'error_code' => $result->errorCode,
            ], $this->errorStatus($result->errorCode));
        }

        return response()->json([
            'status'  => 'success',
            'message' => $result->message,
            'data'    => $result->data,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $result = $this->activityService->getActivityById($id);

        if (! $result->success) {
            return response()->json([
                'status'  => 'error',
                'message' => $result->message,
                'error_code' => $result->errorCode,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status'  => 'success',
            'message' => $result->message,
            'data'    => $result->data,
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validatedData = $request->validate([
            'subject'      => ['sometimes', 'required', 'string', 'max:255'],
            'type'         => ['sometimes', 'required', 'string', 'in:call,meeting,email,task'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'due_date'     => ['sometimes', 'nullable', 'date'],
            // Reopening clears the completion, so an active reopen flag and a
            // completion time cannot travel together.
            'completed_at' => ['sometimes', 'nullable', 'date', 'prohibited_if:reopen,true,1'],
            'reopen'       => ['sometimes', 'boolean'],
        ]);

        $activityUpdateDTO = $this->mapper->mapToUpdateDTO($id, $validatedData);

        $result = $this->activityService->update($activityUpdateDTO);

        if (! $result->success) {
            return response()->json([
                'status'  => 'error',
                'message' => $result->message,
                'error_code' => $result->errorCode,
            ], $this->errorStatus($result->errorCode));
        }

        return response()->json([
            'status'  => 'success',
            'message' => $result->message,
            'data'    => $result->data,
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $result = $this->activityService->deleteActivity($id);

        if (! $result->success) {
            return response()->json([
                'status'  => 'error',
                'message' => $result->message,
                'error_code' => $result->errorCode,
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status'  => 'success',
            'message' => $result->message,
        ], Response::HTTP_OK);
    }

    /**
     * Maps a domain error code to the HTTP status the client should see.
     * A broken activity invariant is a validation failure, not a bad request.
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
}
