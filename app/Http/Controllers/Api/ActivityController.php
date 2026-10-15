<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Activity\StoreActivityRequest;
use App\Models\Activity;
use App\Models\Lead;
use Core\Activity\Application\Contracts\ActivityMapperContract;
use Core\Activity\Application\DTOs\Requests\ActivityFilterDTO;
use Core\Activity\Application\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ActivityController extends Controller
{
    public function __construct(
        private readonly ActivityService $activityService,
        private readonly ActivityMapperContract $mapper
    ){}

    /**
     * Display a listing of the resource with flexible filters.
     */
    public function index(Request $request): JsonResponse
    {

        // ১. Request থেকে ডাটা নিয়ে সরাসরি DTO তৈরি করা (Mapper ব্যবহার করেও করতে পারেন)
        $filterDTO = new ActivityFilterDTO(
            status: $request->input('status'),
            type: $request->input('type'),
            userId: $request->filled('user_id') ? $request->integer('user_id') : null,
            filter: $request->input('filter'),
            perPage: (int) $request->input('per_page', 15)
        );

        // ২. সার্ভিস লেয়ারকে DTO দিয়ে দেওয়া (সার্ভিস জানবে কীভাবে ফিল্টার করে ডাটা আনতে হবে)
        $result = $this->activityService->getActivities($filterDTO);


        // ৩. যদি কোনো কারণে এরর আসে
        if (! $result->success) {
            return response()->json([
                'status'  => 'error',
                'message' => $result->message,
                'error_code' => $result->errorCode ?? 'ACTIVITY_FETCH_FAILED'
            ], Response::HTTP_BAD_REQUEST);
        }

        // ৪. সফল হলে রেসপন্স দেওয়া
        return response()->json([
            'status'  => 'success',
            'message' => $result->message ?? 'Activities retrieved successfully.',
            'data'    => $result->data, 
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreActivityRequest $request)
    {
       $validated = $request->validated(); 

       $validated['user_id'] = auth()->id();

       $activityCreateDTO = $this->mapper->mapToCreateDTO($validated);

       $result = $this->activityService->create($activityCreateDTO); 


        // যদি কোনো কারণে ফেইল করে (Result::failure)
       if (! $result->success) {
           return response()->json([
               'status'  => 'error',
               'message' => $result->message,
               'error_code' => $result->errorCode
           ], Response::HTTP_BAD_REQUEST);
       }

       // সফল হলে (Result::success)
       return response()->json([
            'status'  => 'success',
            'message' => $result->message, // 'Activity created successfully.'
            'data'    => $result->data,    // এটি হলো ActivityResponseDTO
        ], Response::HTTP_CREATED);

    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        // ১. সার্ভিস লেয়ারকে শুধু আইডি দিয়ে দেওয়া
        $result = $this->activityService->getActivityById($id);

        // ২. ডেটা না পেলে (404)
        if (! $result->success) {
            return response()->json([
                'status'  => 'error',
                'message' => $result->message, // 'Activity not found.'
            ], Response::HTTP_NOT_FOUND); 
        }

        // ৩. ডেটা পেলে (200)
        return response()->json([
            'status'  => 'success',
            'message' => $result->message,
            'data'    => $result->data, // এটি ম্যাপ করা ActivityResponseDTO
        ], Response::HTTP_OK); 
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validatedData = $request->validate([
            'subject'      => ['sometimes', 'required', 'string', 'max:255'],
            'type'         => ['sometimes', 'required', 'string', 'in:call,meeting,email,task'],
            'description'  => ['sometimes', 'nullable', 'string'],
            'due_date'     => ['sometimes', 'nullable', 'date'],
            'completed_at' => ['sometimes', 'nullable', 'date'],
        ]);

        // ট্রানজ্যাকশন ও লকিং
        $activity = DB::transaction(function () use ($id, $validatedData) {
            // lockForUpdate() দিয়ে রেকর্ড লক করা
            $record = Activity::where('id', $id)->lockForUpdate()->first();

            if (! $record) {
                return null;
            }

            $record->update($validatedData);

            return $record;
        });

        if (! $activity) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Activity not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Activity updated successfully.',
            'data'    => $activity->fresh(['user', 'trackable']),
        ], Response::HTTP_OK);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        // ১. রেকর্ডটি খুঁজে বের করা
        $activity = Activity::find($id);

        // ২. রেকর্ড না থাকলে ৪০৪ রিটার্ন করা
        if (! $activity) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Activity not found.',
            ], Response::HTTP_NOT_FOUND); // 404
        }

        // ৩. ডিলিট করা
        $activity->delete();

        // ৪. সফল রেসপন্স রিটার্ন করা
        return response()->json([
            'status'  => 'success',
            'message' => 'Activity deleted successfully.',
        ], Response::HTTP_OK); // 200
    }
}
