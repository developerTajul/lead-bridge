<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class ContactController extends Controller
{
    /**
     * Display a listing of contacts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Contact::with(['companies']);

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $contacts = $query->latest()->paginate(15);

        return response()->json([
            'status'  => 'success',
            'message' => 'Contacts retrieved successfully.',
            'data'    => $contacts,
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created contact (with optional company linkage).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'string', 'email', 'max:255', 'unique:contacts,email'],
            'phone'       => ['nullable', 'string', 'max:50'],
            'company_id'  => ['nullable', 'integer', 'exists:companies,id'],
            'job_title'   => ['nullable', 'string', 'max:255'],
            'is_primary'  => ['nullable', 'boolean'],
        ]);

        $contact = DB::transaction(function () use ($validated): Contact {
            $newContact = Contact::create([
                'name'  => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
            ]);

            if (! empty($validated['company_id'])) {
                $newContact->companies()->attach($validated['company_id'], [
                    'job_title'  => $validated['job_title'] ?? null,
                    'is_primary' => $validated['is_primary'] ?? false,
                ]);
            }

            return $newContact;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Contact created successfully.',
            'data'    => $contact->load(['companies']),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified contact.
     */
    public function show(Contact $contact): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => 'Contact retrieved successfully.',
            'data'    => $contact->load(['companies', 'activities']),
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified contact with row locking.
     */
    public function update(Request $request, Contact $contact): JsonResponse
    {
        $validated = $request->validate([
            'name'  => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', 'unique:contacts,email,' . $contact->id],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $updatedContact = DB::transaction(function () use ($contact, $validated): Contact {
            $record = Contact::where('id', $contact->id)->lockForUpdate()->firstOrFail();
            $record->update($validated);

            return $record;
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Contact updated successfully.',
            'data'    => $updatedContact->fresh(['companies']),
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified contact.
     */
    public function destroy(Contact $contact): JsonResponse
    {
        $contact->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Contact deleted successfully.',
        ], Response::HTTP_OK);
    }
}