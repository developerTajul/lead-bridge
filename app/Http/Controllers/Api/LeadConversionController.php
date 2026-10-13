<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadConversion;
use App\Models\Pipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class LeadConversionController extends Controller
{
    /**
     * Convert a qualified lead into Company, Contact, Deal, and Log Conversion.
     */
    public function convert(Request $request, Lead $lead): JsonResponse
    {
        // ১. চেক করা লিডটি ইতোমধ্যে কনভার্ট হয়েছে কি না
        if ($lead->conversion()->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'This lead has already been converted.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // ২. অতিরিক্ত প্যারামিটার অপশনাল রাখা (যেমন ডিল অ্যামাউন্ট বা নির্দিষ্ট পাইপলাইন)
        $validated = $request->validate([
            'deal_title'          => ['nullable', 'string', 'max:255'],
            'amount'              => ['nullable', 'numeric', 'min:0'],
            'currency'            => ['nullable', 'string', 'size:3'],
            'pipeline_id'         => ['nullable', 'integer', 'exists:pipelines,id'],
            'deal_stage_id'       => ['nullable', 'integer', 'exists:deal_stages,id'],
            'expected_close_date' => ['nullable', 'date'],
            'job_title'           => ['nullable', 'string', 'max:255'],
        ]);

        $result = DB::transaction(function () use ($lead, $validated) {
            // রো-লকিং যাতে কনকারেন্ট ডুপ্লিকেট কনভার্সন না ঘটে
            $lockedLead = Lead::where('id', $lead->id)->lockForUpdate()->firstOrFail();

            // ক. কোম্পানি হ্যান্ডলিং (যদি লিডে কোম্পানির নাম থাকে)
            $company = null;
            if (!empty($lockedLead->company_name)) {
                $company = Company::firstOrCreate(
                    ['name' => $lockedLead->company_name],
                    ['phone' => $lockedLead->phone]
                );
            }

            // খ. কন্টাক্ট তৈরি
            $contact = Contact::firstOrCreate(
                ['email' => $lockedLead->email],
                [
                    'name'  => $lockedLead->name,
                    'phone' => $lockedLead->phone,
                ]
            );

            // গ. কোম্পানির সাথে কন্টাক্ট লিংক (Pivot)
            if ($company) {
                $company->contacts()->syncWithoutDetaching([
                    $contact->id => [
                        'job_title'  => $validated['job_title'] ?? 'Contact Person',
                        'is_primary' => true,
                    ],
                ]);
            }

            // ঘ. পাইপলাইন ও স্টেজ নির্বাচন (না দিলে ডিফল্ট পাইপলাইনের ১ম স্টেজ নেওয়া হবে)
            $pipelineId = $validated['pipeline_id'] ?? null;
            $stageId    = $validated['deal_stage_id'] ?? null;

            if (!$pipelineId) {
                $defaultPipeline = Pipeline::where('is_default', true)->with('stages')->first()
                                   ?? Pipeline::with('stages')->first();
                $pipelineId = $defaultPipeline?->id;
                $stageId    = $stageId ?? $defaultPipeline?->stages->first()?->id;
            }

            // ঙ. ডিল তৈরি (যদি পাইপলাইন পাওয়া যায়)
            $deal = null;
            if ($pipelineId && $stageId) {
                $deal = Deal::create([
                    'title'               => $validated['deal_title'] ?? ($lockedLead->company_name ?? $lockedLead->name) . ' - Initial Deal',
                    'company_id'          => $company?->id,
                    'contact_id'          => $contact->id,
                    'amount'              => $validated['amount'] ?? 0.00,
                    'currency'            => $validated['currency'] ?? 'BDT',
                    'pipeline_id'         => $pipelineId,
                    'deal_stage_id'       => $stageId,
                    'expected_close_date' => $validated['expected_close_date'] ?? null,
                ]);
            }

            // চ. লিড স্ট্যাটাস আপডেট
            $lockedLead->update(['status' => 'converted']);

            // ছ. lead_conversions টেবিলে লগ তৈরি
            $conversion = LeadConversion::create([
                'lead_id'      => $lockedLead->id,
                'contact_id'   => $contact->id,
                'company_id'   => $company?->id,
                'deal_id'      => $deal?->id,
                'converted_at' => now(),
            ]);

            return [
                'conversion' => $conversion,
                'company'    => $company,
                'contact'    => $contact,
                'deal'       => $deal,
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Lead converted successfully.',
            'data'    => $result,
        ], Response::HTTP_CREATED);
    }
}