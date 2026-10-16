<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class CompanyController extends Controller
{
    /**
     * Display a listing of companies (Paginated with search/filter).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Company::query();

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('industry', 'like', "%{$search}%");
            });
        }

        $companies = $query->latest()->paginate(15);

        return response()->json([
            'status'  => 'success',
            'message' => 'Companies retrieved successfully.',
            'data'    => $companies,
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created company.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'website'  => ['nullable', 'url', 'max:255'],
            'industry' => ['nullable', 'string', 'max:100'],
            'phone'    => ['nullable', 'string', 'max:50'],
            'address'  => ['nullable', 'string'],
        ]);

        $company = Company::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Company created successfully.',
            'data'    => $company,
        ], Response::HTTP_CREATED);
    }


    /**
     * Display the specified company with 360-degree relationship data.
     */
    public function show(string $id): JsonResponse
    {
        $company = Company::with([
            'contacts',
            'deals.stage',
            'deals.activities.user',
        ])->find($id);

        if (! $company) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Company not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Company profile retrieved successfully.',
            'data'    => $company,
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified company with row locking.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['sometimes', 'required', 'string', 'max:255'],
            'website'  => ['sometimes', 'nullable', 'url', 'max:255'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone'    => ['sometimes', 'nullable', 'string', 'max:50'],
            'address'  => ['sometimes', 'nullable', 'string'],
        ]);

        $company = DB::transaction(function () use ($id, $validated): ?Company {
            $record = Company::where('id', $id)->lockForUpdate()->first();

            if (! $record) {
                return null;
            }

            $record->update($validated);

            return $record;
        });

        if (! $company) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Company not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Company updated successfully.',
            'data'    => $company,
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified company.
     */
    public function destroy(string $id): JsonResponse
    {
        $company = Company::find($id);

        if (! $company) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Company not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        $company->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Company deleted successfully.',
        ], Response::HTTP_OK);
    }
}