<?php

namespace App\Services\Gym;

use App\Models\Invoice;
use App\Models\MemberMembership;
use App\Models\Notification;
use App\Models\NotificationLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    /**
     * Run all notification checks for a tenant.
     */
    public function runAllChecks(int $tenantId): array
    {
        $counts = [
            'expiring' => $this->generateExpiringMembershipAlerts($tenantId),
            'payments' => $this->generatePendingPaymentAlerts($tenantId),
            'renewals' => $this->generateRenewalReminders($tenantId),
        ];

        $this->markExpiredMemberships($tenantId);

        return $counts;
    }

    /**
     * Detect memberships ending within the next 5 days and generate alerts.
     */
    public function generateExpiringMembershipAlerts(int $tenantId): int
    {
        $today = Carbon::today();
        $fiveDaysLater = Carbon::today()->addDays(5);

        $memberships = MemberMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereBetween('end_date', [$today->addDay(), $fiveDaysLater])
            ->with(['member.user', 'plan'])
            ->get();

        $created = 0;

        foreach ($memberships as $membership) {
            $daysLeft = Carbon::today()->diffInDays($membership->end_date, false);
            if ($daysLeft <= 0) {
                continue;
            }

            $memberName = $membership->member?->user?->name ?? 'Member';
            $planName = $membership->plan?->name ?? 'Plan';
            $dedupKey = "expiring:membership:{$membership->id}:{$membership->end_date->toDateString()}";

            $priority = match (true) {
                $daysLeft <= 1 => 'critical',
                $daysLeft <= 3 => 'high',
                default => 'medium',
            };

            $notification = $this->createIfNotExists($tenantId, [
                'user_id' => $this->getAdminUserId($tenantId),
                'notifiable_type' => MemberMembership::class,
                'notifiable_id' => $membership->id,
                'title' => "Membership Expiring in {$daysLeft} day" . ($daysLeft > 1 ? 's' : ''),
                'message' => "{$memberName}'s {$planName} membership expires on {$membership->end_date->format('d M Y')}. Follow up for renewal.",
                'type' => 'warning',
                'category' => 'renewal',
                'priority' => $priority,
                'dedup_key' => $dedupKey,
                'data' => [
                    'member_id' => $membership->member_id,
                    'member_name' => $memberName,
                    'membership_id' => $membership->id,
                    'plan_name' => $planName,
                    'end_date' => $membership->end_date->toDateString(),
                    'days_remaining' => $daysLeft,
                    'final_amount' => (float) $membership->final_amount,
                ],
                'expires_at' => $membership->end_date->copy()->addDays(7),
            ]);

            if ($notification) {
                $created++;
                $this->logNotification($notification, 'notification_created');
            }
        }

        return $created;
    }

    /**
     * Detect unpaid/overdue invoices and notify admin.
     */
    public function generatePendingPaymentAlerts(int $tenantId): int
    {
        $invoices = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('member_id')
            ->whereIn('status', ['unpaid', 'overdue'])
            ->with(['member.user', 'membership.plan'])
            ->get();

        $created = 0;

        foreach ($invoices as $invoice) {
            $memberName = $invoice->member?->user?->name ?? 'Member';
            $invoiceNumber = $invoice->invoice_number ?? "INV-{$invoice->id}";
            $isOverdue = $invoice->status === 'overdue';
            $dedupKey = "payment:invoice:{$invoice->id}";

            $notification = $this->createIfNotExists($tenantId, [
                'user_id' => $this->getAdminUserId($tenantId),
                'notifiable_type' => Invoice::class,
                'notifiable_id' => $invoice->id,
                'title' => $isOverdue ? "Overdue Payment: {$invoiceNumber}" : "Payment Pending: {$invoiceNumber}",
                'message' => "{$memberName} has " . ($isOverdue ? 'an overdue' : 'a pending') . " payment of ₹" . number_format((float) $invoice->final_amount, 0) . " (Invoice: {$invoiceNumber}).",
                'type' => $isOverdue ? 'error' : 'warning',
                'category' => 'payment',
                'priority' => $isOverdue ? 'high' : 'medium',
                'dedup_key' => $dedupKey,
                'data' => [
                    'member_id' => $invoice->member_id,
                    'member_name' => $memberName,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoiceNumber,
                    'amount' => (float) $invoice->final_amount,
                    'due_date' => $invoice->due_date?->toDateString(),
                    'status' => $invoice->status,
                    'membership_id' => $invoice->membership_id,
                    'plan_name' => $invoice->membership?->plan?->name,
                ],
            ]);

            if ($notification) {
                $created++;
                $this->logNotification($notification, 'notification_created');
            }
        }

        return $created;
    }

    /**
     * Generate renewal reminders: expiring today + specific day thresholds.
     */
    public function generateRenewalReminders(int $tenantId): int
    {
        $today = Carbon::today();
        $created = 0;

        // Memberships expiring today
        $expiringToday = MemberMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereDate('end_date', $today)
            ->with(['member.user', 'plan'])
            ->get();

        foreach ($expiringToday as $membership) {
            $memberName = $membership->member?->user?->name ?? 'Member';
            $planName = $membership->plan?->name ?? 'Plan';
            $dedupKey = "reminder:today:membership:{$membership->id}:{$today->toDateString()}";

            $notification = $this->createIfNotExists($tenantId, [
                'user_id' => $this->getAdminUserId($tenantId),
                'notifiable_type' => MemberMembership::class,
                'notifiable_id' => $membership->id,
                'title' => "Membership Expires Today!",
                'message' => "{$memberName}'s {$planName} membership expires today. Immediate renewal action needed.",
                'type' => 'error',
                'category' => 'renewal',
                'priority' => 'critical',
                'dedup_key' => $dedupKey,
                'data' => [
                    'member_id' => $membership->member_id,
                    'member_name' => $memberName,
                    'membership_id' => $membership->id,
                    'plan_name' => $planName,
                    'end_date' => $membership->end_date->toDateString(),
                    'days_remaining' => 0,
                    'final_amount' => (float) $membership->final_amount,
                ],
                'expires_at' => $today->copy()->addDays(7),
            ]);

            if ($notification) {
                $created++;
                $this->logNotification($notification, 'renewal_reminder_today');
            }
        }

        return $created;
    }

    /**
     * Auto-expire active memberships whose end_date has passed.
     */
    public function markExpiredMemberships(int $tenantId): int
    {
        return MemberMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereDate('end_date', '<', Carbon::today())
            ->update(['status' => 'expired']);
    }

    /**
     * Get paginated notifications for a tenant.
     */
    public function getNotifications(int $tenantId, array $filters = [])
    {
        $query = Notification::query()
            ->forTenant($tenantId)
            ->active()
            ->latest();

        if (! empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        if (isset($filters['read'])) {
            $query->where('read', filter_var($filters['read'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->paginate($perPage);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(int $tenantId, int $notificationId): ?Notification
    {
        $notification = Notification::query()
            ->forTenant($tenantId)
            ->where('id', $notificationId)
            ->first();

        if ($notification) {
            $notification->update(['read' => true]);
            $this->logNotification($notification, 'marked_read');
        }

        return $notification;
    }

    /**
     * Mark all notifications as read for a tenant.
     */
    public function markAllAsRead(int $tenantId): int
    {
        return Notification::query()
            ->forTenant($tenantId)
            ->unread()
            ->update(['read' => true]);
    }

    /**
     * Get alert counts for dashboard badges.
     */
    public function getAlertCounts(int $tenantId): array
    {
        $today = Carbon::today();
        $fiveDaysLater = Carbon::today()->addDays(5);

        $expiringSoonCount = MemberMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereBetween('end_date', [$today, $fiveDaysLater])
            ->count();

        $pendingPaymentsCount = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('member_id')
            ->whereIn('status', ['unpaid', 'overdue'])
            ->count();

        $unreadCount = Notification::query()
            ->forTenant($tenantId)
            ->unread()
            ->active()
            ->count();

        $overdueCount = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('member_id')
            ->where('status', 'overdue')
            ->count();

        return [
            'expiring_soon' => $expiringSoonCount,
            'pending_payments' => $pendingPaymentsCount,
            'overdue_payments' => $overdueCount,
            'unread_notifications' => $unreadCount,
        ];
    }

    /**
     * Create notification only if dedup_key doesn't already exist.
     */
    protected function createIfNotExists(int $tenantId, array $attributes): ?Notification
    {
        $dedupKey = $attributes['dedup_key'] ?? null;

        if ($dedupKey) {
            $exists = Notification::query()
                ->where('tenant_id', $tenantId)
                ->where('dedup_key', $dedupKey)
                ->exists();

            if ($exists) {
                return null;
            }
        }

        $attributes['tenant_id'] = $tenantId;
        $attributes['channel'] = $attributes['channel'] ?? 'in_app';

        return Notification::query()->create($attributes);
    }

    /**
     * Log a notification event.
     */
    protected function logNotification(Notification $notification, string $eventType, string $status = 'success'): void
    {
        NotificationLog::query()->create([
            'tenant_id' => $notification->tenant_id,
            'notification_id' => $notification->id,
            'channel' => $notification->channel,
            'recipient' => $notification->user_id ? "user:{$notification->user_id}" : null,
            'event_type' => $eventType,
            'payload' => json_encode($notification->data),
            'status' => $status,
        ]);
    }

    /**
     * Get the primary admin user_id for a tenant.
     */
    protected function getAdminUserId(int $tenantId): int
    {
        static $cache = [];

        if (isset($cache[$tenantId])) {
            return $cache[$tenantId];
        }

        $user = User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['Gym Admin', 'Super Admin', 'Manager']))
            ->orderBy('id')
            ->first();

        $cache[$tenantId] = $user?->id ?? User::query()->where('tenant_id', $tenantId)->orderBy('id')->value('id') ?? 0;

        return $cache[$tenantId];
    }
}
