<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class DashboardController extends Controller
{
    /**
     * Get high-level CRM performance metrics for executive overview.
     */
    public function metrics(): JsonResponse
    {
        // ১. ডিল ও রেভিনিউ মেট্রিক্স
        $totalDeals = Deal::count();
        $wonDealsCount = Deal::where('deal_stage_id', 5)->count();
        $lostDealsCount = Deal::where('deal_stage_id', 6)->count();
        $openDealsCount = Deal::whereNotIn('deal_stage_id', [5, 6])->count();

        $totalWonRevenue = (float) Deal::where('deal_stage_id', 5)->sum('amount');
        $openPipelineValue = (float) Deal::whereNotIn('deal_stage_id', [5, 6])->sum('amount');

        // ২. লিড ও কনভার্সন মেট্রিক্স
        $totalLeads = Lead::count();
        $convertedLeads = Lead::where('status', 'converted')->count();
        $conversionRate = $totalLeads > 0 
            ? round(($convertedLeads / $totalLeads) * 100, 2) 
            : 0.0;

        // ৩. অ্যাক্টিভিটি মেট্রিক্স
        $pendingActivitiesCount = Activity::whereNull('completed_at')->count();
        $completedActivitiesCount = Activity::whereNotNull('completed_at')->count();

        return response()->json([
            'status'  => 'success',
            'message' => 'Dashboard metrics retrieved successfully.',
            'data'    => [
                'revenue' => [
                    'won_revenue'         => $totalWonRevenue,
                    'open_pipeline_value' => $openPipelineValue,
                    'currency'            => 'BDT',
                ],
                'deals' => [
                    'total_deals' => $totalDeals,
                    'won_deals'   => $wonDealsCount,
                    'lost_deals'  => $lostDealsCount,
                    'open_deals'  => $openDealsCount,
                    'win_rate'    => ($wonDealsCount + $lostDealsCount) > 0 
                        ? round(($wonDealsCount / ($wonDealsCount + $lostDealsCount)) * 100, 2) 
                        : 0.0,
                ],
                'leads' => [
                    'total_leads'     => $totalLeads,
                    'converted_leads' => $convertedLeads,
                    'conversion_rate' => $conversionRate,
                ],
                'activities' => [
                    'pending'   => $pendingActivitiesCount,
                    'completed' => $completedActivitiesCount,
                ],
            ],
        ], Response::HTTP_OK);
    }
}