<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\CouponController;
use App\Http\Controllers\SuperAdmin\GymController;
use App\Http\Controllers\SuperAdmin\PaymentController;
use App\Http\Controllers\SuperAdmin\PlatformPlanController;
use App\Http\Controllers\SuperAdmin\ReportController;
use App\Http\Controllers\SuperAdmin\SettingsController;
use App\Http\Controllers\SuperAdmin\SubscriptionController;
use App\Http\Controllers\SuperAdmin\SuperAdminDashboardController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:10,1');
Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::post('email/verify', [AuthController::class, 'verifyEmail'])->middleware('throttle:5,1');

Route::middleware(['jwt', 'auth.custom'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [ProfileController::class, 'me']);

    Route::prefix('super-admin')
        ->middleware('role:Super Admin')
        ->group(function () {
            Route::get('dashboard', SuperAdminDashboardController::class);

            Route::get('gyms', [GymController::class, 'index']);
            Route::post('gyms', [GymController::class, 'store']);
            Route::get('gyms/{tenant}', [GymController::class, 'show']);
            Route::post('gyms/{tenant}/suspend', [GymController::class, 'suspend']);
            Route::post('gyms/{tenant}/activate', [GymController::class, 'activate']);
            Route::post('gyms/{tenant}', [GymController::class, 'update']);
            Route::delete('gyms/{tenant}', [GymController::class, 'destroy']);

            Route::get('plans', [PlatformPlanController::class, 'index']);
            Route::post('plans', [PlatformPlanController::class, 'store']);
            Route::get('plans/{platformPlan}', [PlatformPlanController::class, 'show']);
            Route::put('plans/{platformPlan}', [PlatformPlanController::class, 'update']);
            Route::delete('plans/{platformPlan}', [PlatformPlanController::class, 'destroy']);
            Route::post('plans/{platformPlan}/activate', [PlatformPlanController::class, 'activate']);
            Route::post('plans/{platformPlan}/deactivate', [PlatformPlanController::class, 'deactivate']);

            Route::get('subscriptions', [SubscriptionController::class, 'index']);
            Route::post('subscriptions', [SubscriptionController::class, 'store']);
            Route::get('subscriptions/{tenantSubscription}', [SubscriptionController::class, 'show']);
            Route::post('subscriptions/{tenantSubscription}/renew', [SubscriptionController::class, 'renew']);
            Route::post('subscriptions/{tenantSubscription}/cancel', [SubscriptionController::class, 'cancel']);
            Route::post('subscriptions/{tenantSubscription}/change-plan', [SubscriptionController::class, 'changePlan']);

            Route::get('coupons', [CouponController::class, 'index']);
            Route::post('coupons', [CouponController::class, 'store']);
            Route::get('coupons/{coupon}', [CouponController::class, 'show']);
            Route::put('coupons/{coupon}', [CouponController::class, 'update']);
            Route::delete('coupons/{coupon}', [CouponController::class, 'destroy']);

            Route::get('payments', [PaymentController::class, 'index']);

            Route::get('reports/overview', [ReportController::class, 'overview']);
            Route::get('reports/revenue', [ReportController::class, 'revenue']);
            Route::get('reports/gym-growth', [ReportController::class, 'gymGrowth']);
            Route::get('reports/subscriptions', [ReportController::class, 'subscriptions']);

            Route::get('settings/summary', SettingsController::class);
        });
});
