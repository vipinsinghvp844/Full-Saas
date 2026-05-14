<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class TenantSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'plan_id',
        'coupon_id',
        'status',
        'start_date',
        'end_date',
        'renewal_date',
        'next_billing_date',
        'grace_period_ends_at',
        'paused_at',
        'resumed_at',
        'payment_status',
        'payment_method',
        'price',
        'discount_amount',
        'final_amount',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'renewal_date' => 'date',
        'next_billing_date' => 'date',
        'grace_period_ends_at' => 'datetime',
        'paused_at' => 'datetime',
        'resumed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    public function tenant(): BelongsTo 
    { 
        return $this->belongsTo(Tenant::class); 
    }

    public function plan(): BelongsTo 
    { 
        return $this->belongsTo(PlatformPlan::class, 'plan_id'); 
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'subscription_id');
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Invoice::class, 'subscription_id', 'invoice_id');
    }

    public function pause(): self
    {
        $this->update([
            'status' => 'paused',
            'paused_at' => now(),
        ]);

        return $this->refresh();
    }

    public function resume(): self
    {
        $this->update([
            'status' => 'active',
            'resumed_at' => now(),
        ]);

        return $this->refresh();
    }

    public function suspend(): self
    {
        $this->update([
            'status' => 'suspended',
        ]);

        return $this->refresh();
    }
}