<?php

namespace App\Console\Commands;

use App\Models\TenantSubscription;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:process-expired')]
#[Description('Process expired subscriptions and apply grace periods or suspensions')]
class ProcessExpiredSubscriptions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Processing expired subscriptions...');

        // 1. Find subscriptions that have expired but are still marked as active
        $expiredSubscriptions = TenantSubscription::where('status', 'active')
            ->where('end_date', '<', now())
            ->get();

        $this->info("Found {$expiredSubscriptions->count()} expired subscriptions");

        foreach ($expiredSubscriptions as $subscription) {
            $this->line("Processing subscription ID: {$subscription->id} for tenant: {$subscription->tenant->name}");
            $subscription->expire();
        }

        // 2. Find subscriptions in grace period that have exceeded grace period
        $gracePeriodExpired = TenantSubscription::where('status', 'expired')
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<', now())
            ->get();

        $this->info("Found {$gracePeriodExpired->count()} subscriptions past grace period");

        foreach ($gracePeriodExpired as $subscription) {
            $this->line("Suspending subscription ID: {$subscription->id} for tenant: {$subscription->tenant->name}");
            $subscription->suspend();
        }

        $this->info('Expired subscriptions processing completed.');
    }
}
