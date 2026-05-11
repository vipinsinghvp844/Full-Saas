<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantSubscription extends Model
{
    /** @use HasFactory<\Database\Factories\TenantSubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'coupon_id',
        'start_date',
        'end_date',
        'status',
        'price',
        'discount_amount',
        'final_amount',
        'payment_method',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'cancelled_at' => 'datetime',
        'price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan()
    {
        return $this->belongsTo(PlatformPlan::class, 'plan_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'subscription_id');
    }
}
