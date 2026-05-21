<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\CouponController;
use App\Http\Controllers\SuperAdmin\GymController;
use App\Http\Controllers\SuperAdmin\PaymentController;
use App\Http\Controllers\SuperAdmin\PlatformPlanController;
use App\Http\Controllers\Gym\AttendanceController as GymAttendanceController;
use App\Http\Controllers\Gym\BillingController as GymBillingController;
use App\Http\Controllers\Gym\ClassController as GymClassController;
use App\Http\Controllers\Gym\DashboardController as GymDashboardController;
use App\Http\Controllers\Gym\EmployeeController as GymEmployeeController;
use App\Http\Controllers\Gym\MemberController as GymMemberController;
use App\Http\Controllers\Gym\MembershipPlanController as GymMembershipPlanController;
use App\Http\Controllers\Gym\NotificationController as GymNotificationController;
use App\Http\Controllers\Gym\ReportController as GymReportController;
use App\Http\Controllers\Gym\TrainerController as GymTrainerController;
use App\Http\Controllers\Gym\ExpenseController as GymExpenseController;
use App\Http\Controllers\Gym\PayrollController as GymPayrollController;
use App\Http\Controllers\Gym\SettingsController as GymSettingsController;
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

Route::get('cms/content', [\App\Http\Controllers\PublicCmsController::class, 'index']);

// Public Gym Listing & Profile Pages
Route::get('gyms', [\App\Http\Controllers\PublicGymController::class, 'index']);
Route::get('gyms/{slug}', [\App\Http\Controllers\PublicGymController::class, 'show']);
Route::post('gyms/{slug}/feedback', [\App\Http\Controllers\PublicGymController::class, 'submitFeedback'])->middleware('throttle:10,1');
Route::post('gyms/{slug}/subscribe', [\App\Http\Controllers\PublicGymController::class, 'subscribePlan'])->middleware('throttle:5,1');

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
            Route::post('subscriptions/{tenantSubscription}/pause', [SubscriptionController::class, 'pause']);
            Route::post('subscriptions/{tenantSubscription}/resume', [SubscriptionController::class, 'resume']);
            Route::post('subscriptions/{tenantSubscription}/suspend', [SubscriptionController::class, 'suspend']);

            Route::get('coupons', [CouponController::class, 'index']);
            Route::post('coupons', [CouponController::class, 'store']);
            Route::get('coupons/{coupon}', [CouponController::class, 'show']);
            Route::put('coupons/{coupon}', [CouponController::class, 'update']);
            Route::delete('coupons/{coupon}', [CouponController::class, 'destroy']);
            Route::post('coupons/{coupon}/activate', [CouponController::class, 'activate']);
            Route::post('coupons/{coupon}/deactivate', [CouponController::class, 'deactivate']);

            Route::get('payments', [PaymentController::class, 'index']);
            Route::get('payments/{payment}', [PaymentController::class, 'show']);

            Route::get('reports/overview', [ReportController::class, 'overview']);
            Route::get('reports/revenue', [ReportController::class, 'revenue']);
            Route::get('reports/gym-growth', [ReportController::class, 'gymGrowth']);
            Route::get('reports/subscriptions', [ReportController::class, 'subscriptions']);
            Route::get('reports/coupons', [ReportController::class, 'coupons']);
            Route::get('reports/payments', [ReportController::class, 'payments']);
            Route::get('reports/growth', [ReportController::class, 'growth']);

            Route::get('settings', [SettingsController::class, 'index']);
            Route::put('settings/{group}', [SettingsController::class, 'update']);
            Route::post('settings/upload-media', [SettingsController::class, 'uploadMedia']);

            // Support Tickets for Super Admin
            Route::get('support/tickets', [\App\Http\Controllers\SuperAdmin\SupportTicketController::class, 'index']);
            Route::get('support/tickets/{id}', [\App\Http\Controllers\SuperAdmin\SupportTicketController::class, 'show']);
            Route::post('support/tickets/{id}/reply', [\App\Http\Controllers\SuperAdmin\SupportTicketController::class, 'reply']);
            Route::put('support/tickets/{id}/status', [\App\Http\Controllers\SuperAdmin\SupportTicketController::class, 'updateStatus']);
            Route::post('support/upload', [\App\Http\Controllers\SuperAdmin\SupportTicketController::class, 'uploadAttachment']);
        });

    // Gym Routes - Require active SaaS subscription
    Route::prefix('gym')
        ->middleware(['role:Gym Admin|Manager|Receptionist|Accountant', 'subscription'])
        ->group(function () {
            Route::get('dashboard', [GymDashboardController::class, 'index']);

            // Members
            Route::get('members', [GymMemberController::class, 'index']);
            Route::post('members', [GymMemberController::class, 'store']);
            Route::get('members/{member}', [GymMemberController::class, 'show']);
            Route::put('members/{member}', [GymMemberController::class, 'update']);
            Route::delete('members/{member}', [GymMemberController::class, 'destroy']);
            Route::post('members/{member}/suspend', [GymMemberController::class, 'suspend']);
            Route::post('members/{member}/renew', [GymMemberController::class, 'renew']);

            // Staff (Employees)
            Route::get('staff', [GymEmployeeController::class, 'index']);
            Route::post('staff', [GymEmployeeController::class, 'store']);
            Route::get('staff/{employee}', [GymEmployeeController::class, 'show']);
            Route::put('staff/{employee}', [GymEmployeeController::class, 'update']);
            Route::delete('staff/{employee}', [GymEmployeeController::class, 'destroy']);

            // Trainers (CRUD + assign members)
            Route::get('trainers', [GymTrainerController::class, 'index']);
            Route::post('trainers', [GymTrainerController::class, 'store']);
            Route::get('trainers/{trainer}', [GymTrainerController::class, 'show']);
            Route::put('trainers/{trainer}', [GymTrainerController::class, 'update']);
            Route::delete('trainers/{trainer}', [GymTrainerController::class, 'destroy']);
            Route::post('trainers/{trainer}/assign-members', [GymTrainerController::class, 'assignMembers']);

            // Attendance
            Route::get('attendance', [GymAttendanceController::class, 'index']);
            Route::get('attendance/today', [GymAttendanceController::class, 'today']);
            Route::post('attendance/check-in', [GymAttendanceController::class, 'checkIn']);
            Route::post('attendance/{attendance}/check-out', [GymAttendanceController::class, 'checkOut']);

            // Billing / Payments
            Route::get('billing/dashboard', [GymBillingController::class, 'dashboard']);
            Route::get('billing/payments', [GymBillingController::class, 'payments']);
            Route::post('billing/payments', [GymBillingController::class, 'storePayment']);
            Route::get('billing/invoices', [GymBillingController::class, 'invoices']);

            // Notifications
            Route::get('notifications', [GymNotificationController::class, 'index']);
            Route::post('notifications/generate', [GymNotificationController::class, 'generate']);
            Route::put('notifications/read-all', [GymNotificationController::class, 'markAllRead']);
            Route::put('notifications/{id}/read', [GymNotificationController::class, 'markRead']);

            // Reports & Analytics
            Route::prefix('reports')->group(function () {
                Route::get('overview', [GymReportController::class, 'overview']);
                Route::get('revenue', [GymReportController::class, 'revenue']);
                Route::get('memberships', [GymReportController::class, 'memberships']);
                Route::get('attendance', [GymReportController::class, 'attendance']);
                Route::get('trainers', [GymReportController::class, 'trainers']);
            });

            // Classes / Membership plans (list endpoints today)
            Route::get('classes', [GymClassController::class, 'index']);
            Route::post('classes', [GymClassController::class, 'store']);
            Route::get('classes/{class}', [GymClassController::class, 'show']);
            Route::put('classes/{id}', [GymClassController::class, 'update']);
            Route::delete('classes/{id}', [GymClassController::class, 'destroy']);
            Route::post('classes/{class}/book', [GymClassController::class, 'book']);
            Route::put('classes/bookings/{bookingId}/status', [GymClassController::class, 'updateBookingStatus']);
            Route::get('membership-plans', [GymMembershipPlanController::class, 'index']);
            Route::post('membership-plans', [GymMembershipPlanController::class, 'store']);
            Route::put('membership-plans/{id}', [GymMembershipPlanController::class, 'update']);
            Route::delete('membership-plans/{id}', [GymMembershipPlanController::class, 'destroy']);

            // Expenses
            Route::get('expenses/dashboard', [GymExpenseController::class, 'dashboard']);
            Route::get('expenses/categories', [GymExpenseController::class, 'categoryIndex']);
            Route::post('expenses/categories', [GymExpenseController::class, 'categoryStore']);
            Route::post('expenses/categories/seed-defaults', [GymExpenseController::class, 'seedDefaultCategories']);
            Route::put('expenses/categories/{id}', [GymExpenseController::class, 'categoryUpdate']);
            Route::delete('expenses/categories/{id}', [GymExpenseController::class, 'categoryDestroy']);
            Route::get('expenses', [GymExpenseController::class, 'index']);
            Route::post('expenses', [GymExpenseController::class, 'store']);
            Route::put('expenses/{id}', [GymExpenseController::class, 'update']);
            Route::delete('expenses/{id}', [GymExpenseController::class, 'destroy']);

            // Payroll
            Route::get('payroll/dashboard', [GymPayrollController::class, 'dashboard']);
            Route::get('payroll', [GymPayrollController::class, 'index']);
            Route::post('payroll/generate', [GymPayrollController::class, 'generate']);
            Route::put('payroll/{id}', [GymPayrollController::class, 'update']);
            Route::post('payroll/{id}/mark-paid', [GymPayrollController::class, 'markPaid']);
            Route::delete('payroll/{id}', [GymPayrollController::class, 'destroy']);

            // Settings — Profile
            Route::get('settings/profile', [GymSettingsController::class, 'getProfile']);
            Route::put('settings/profile', [GymSettingsController::class, 'updateProfile']);
            Route::post('settings/profile/logo', [GymSettingsController::class, 'uploadLogo']);
            Route::post('settings/upload-media', [GymSettingsController::class, 'uploadMedia']);

            // Settings — Payment Gateway
            Route::get('settings/payment', [GymSettingsController::class, 'getPaymentSettings']);
            Route::put('settings/payment', [GymSettingsController::class, 'updatePaymentSettings']);

            // Settings — General KV (Billing, Notifications, Advanced)
            Route::get('settings/kv', [GymSettingsController::class, 'getSettings']);
            Route::put('settings/kv', [GymSettingsController::class, 'updateSettings']);

            // Settings — Users
            Route::get('settings/users', [GymSettingsController::class, 'getUsers']);
            Route::post('settings/users', [GymSettingsController::class, 'createUser']);
            Route::put('settings/users/{id}', [GymSettingsController::class, 'updateUser']);
            Route::patch('settings/users/{id}/toggle-status', [GymSettingsController::class, 'toggleUserStatus']);
            Route::get('settings/roles', [GymSettingsController::class, 'getRoles']);
        });

    // Gym Routes - Exempt from subscription middleware (so they can actually subscribe)
    Route::prefix('gym')
        ->middleware(['role:Gym Admin|Manager|Receptionist|Accountant'])
        ->group(function () {
            // Platform Subscriptions
            Route::get('platform/plans', [\App\Http\Controllers\Gym\PlatformSubscriptionController::class, 'plans']);
            Route::get('platform/subscription', [\App\Http\Controllers\Gym\PlatformSubscriptionController::class, 'current']);
            Route::post('platform/coupon/validate', [\App\Http\Controllers\Gym\PlatformSubscriptionController::class, 'validateCoupon']);
            Route::post('platform/subscribe', [\App\Http\Controllers\Gym\PlatformSubscriptionController::class, 'subscribe']);
            Route::post('platform/stripe/confirm', [\App\Http\Controllers\Gym\PlatformSubscriptionController::class, 'confirmStripeCheckout']);
            Route::post('platform/razorpay/confirm', [\App\Http\Controllers\Gym\PlatformSubscriptionController::class, 'confirmRazorpayPayment']);
            Route::get('notifications/counts', [GymNotificationController::class, 'counts']);

            // Support Tickets for Gym Admin
            Route::get('support/tickets', [\App\Http\Controllers\Gym\SupportTicketController::class, 'index']);
            Route::post('support/tickets', [\App\Http\Controllers\Gym\SupportTicketController::class, 'store']);
            Route::get('support/tickets/{id}', [\App\Http\Controllers\Gym\SupportTicketController::class, 'show']);
            Route::post('support/tickets/{id}/reply', [\App\Http\Controllers\Gym\SupportTicketController::class, 'reply']);
            Route::post('support/upload', [\App\Http\Controllers\Gym\SupportTicketController::class, 'uploadAttachment']);
        });
});

// Webhooks
Route::post('webhooks/stripe', [\App\Http\Controllers\Webhooks\StripeWebhookController::class, 'handle']);
Route::post('webhooks/razorpay', [\App\Http\Controllers\Webhooks\RazorpayWebhookController::class, 'handle']);
