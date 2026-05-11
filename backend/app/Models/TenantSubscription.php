<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model
{
    protected $fillable = [
        'tenant_id', 'plan_id', 'status', 'start_date', 'end_date', 
        'renewal_date', 'next_billing_date', 'payment_status', 'final_amount'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'renewal_date' => 'date',
        'next_billing_date' => 'date',
        'final_amount' => 'float',
    ];

    public function tenant(): BelongsTo 
    { 
        return $this->belongsTo(Tenant::class); 
    }

    public function plan(): BelongsTo 
    { 
        return $this->belongsTo(PlatformPlan::class, 'plan_id'); 
    }
}