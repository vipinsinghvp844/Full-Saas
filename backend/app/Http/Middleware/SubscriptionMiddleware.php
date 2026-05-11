<?php

namespace App\Http\Middleware;

use App\Models\TenantSubscription;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        // Allow super admins to bypass subscription checks
        if ($user && $user->hasRole('Super Admin')) {
            return $next($request);
        }

        // For gym users, check their tenant's active subscription
        if ($user && $user->tenant_id) {
            $activeSubscription = TenantSubscription::where('tenant_id', $user->tenant_id)
                ->whereIn('status', ['active', 'trial'])
                ->where(function ($query) {
                    $query->where('end_date', '>', now())
                          ->orWhere(function ($q) {
                              $q->where('status', 'expired')
                                ->where('grace_period_ends_at', '>', now());
                          });
                })
                ->first();

            if (!$activeSubscription) {
                return response()->json([
                    'message' => 'Subscription expired. Please renew your subscription to continue.',
                    'error' => 'subscription_expired',
                    'data' => [
                        'subscription_required' => true,
                        'renewal_url' => '/subscription/renew'
                    ]
                ], 403);
            }
        }

        return $next($request);
    }
}
