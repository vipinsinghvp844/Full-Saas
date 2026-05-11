<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\SubscriptionResource;
use App\Models\TenantSubscription;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SubscriptionController extends Controller
{
    /**
     * Display a listing of all subscriptions with tenant and plan details.
     */
    public function index(Request $request)
    {
        $subscriptions = TenantSubscription::with(['tenant', 'plan'])
            ->latest()
            ->paginate($request->get('per_page', 10));

        return SubscriptionResource::collection($subscriptions);
    }

    /**
     * Get subscription analytics for dashboard cards.
     */
    public function stats()
    {
        $now = Carbon::now();
        
        $activeCount = TenantSubscription::where('status', 'active')->count();
        $expiredCount = TenantSubscription::where('status', 'expired')->count();
        $trialCount = TenantSubscription::where('status', 'trial')->count();

        // Calculate Monthly Recurring Revenue (MRR)
        // We normalize yearly/quarterly plans to their monthly value
        $mrr = TenantSubscription::where('status', 'active')
            ->with('plan')
            ->get()
            ->sum(function ($sub) {
                $months = $sub->plan->duration ?: 1;
                return $sub->final_amount / $months;
            });

        // Renewals this month
        $renewalsThisMonth = TenantSubscription::where('status', 'active')
            ->whereMonth('end_date', $now->month)
            ->whereYear('end_date', $now->year)
            ->count();

        return response()->json([
            'data' => [
                'active_subscriptions' => $activeCount,
                'expired_subscriptions' => $expiredCount,
                'trial_subscriptions' => $trialCount,
                'mrr' => round($mrr, 2),
                'renewals_this_month' => $renewalsThisMonth,
                'growth_percentage' => 12.5, // Mock value for UI polish
            ]
        ]);
    }

    /**
     * Get details for a specific subscription.
     */
    public function show(TenantSubscription $subscription)
    {
        return new SubscriptionResource($subscription->load(['tenant', 'plan']));
    }
}