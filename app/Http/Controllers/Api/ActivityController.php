<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    /**
     * Display a listing of the resource with flexible filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Activity::with(['user', 'trackable']);

        // ১. স্ট্যাটাস অনুযায়ী ফিল্টার (pending / completed)
        if ($request->filled('status')) {
            $status = (string) $request->input('status');
            if ($status === 'pending') {
                $query->whereNull('completed_at');
            } elseif ($status === 'completed') {
                $query->whereNotNull('completed_at');
            }
        }

        // ২. টাইপ ফিল্টার (call, meeting, task ইত্যাদি)
        if ($request->filled('type')) {
            $query->where('type', (string) $request->input('type'));
        }

        // ৩. নির্দিষ্ট ইউজার ফিল্টার
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        // ৪. সময়ভিত্তিক ফিল্টার (today / overdue)
        if ($request->filled('filter')) {
            $filter = (string) $request->input('filter');
            $today = now()->toDateString();

            if ($filter === 'today') {
                $query->whereDate('due_date', $today);
            } elseif ($filter === 'overdue') {
                $query->whereNull('completed_at')
                      ->where('due_date', '<', now());
            }
        }

        $activities = $query->orderBy('due_date', 'asc')->paginate(15);

        return response()->json([
            'status'  => 'success',
            'message' => 'Activities retrieved successfully.',
            'data'    => $activities,
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
       $validated = $request->validate([
           'trackable_type' => ['required', 'string', 'max:255'],
           'trackable_id' => ['required', 'integer'],
           'type' => ['required', 'string', 'max:255'],
           'subject' => ['required', 'string', 'max:255'],
           'description' => ['required', 'string', 'max:255'],
           'due_date' => ['required', 'date'],
       ]); 

       $validated['user_id'] = auth()->id();

       $activity = Activity::create($validated);

       if ($activity->trackable_type === Lead::class || $activity->trackable_type === 'App\Models\Lead') {
            Lead::where('id', $activity->trackable_id)
                ->where('status', 'new')
                ->update(['status' => 'contacted']);
        }

       return response()->json([
            'status'  => 'success',
            'message' => 'Activity created and lead status updated successfully.',
            'data'    => $activity,
        ], Response::HTTP_CREATED);

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $activity = Activity::with(['user', 'trackable'])->find($id);

        if (! $activity) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Activity not found.',
            ], Response::HTTP_NOT_FOUND); // 404
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Activity retrieved successfully.',
            'data'    => $activity,
        ], Response::HTTP_OK); // 200
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
