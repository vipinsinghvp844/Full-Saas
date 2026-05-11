<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'code',
        'discount_type',
        'discount_value',
        'valid_from',
        'valid_to',
        'usage_limit',
        'used_count',
        'status',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function isExpired(): bool
    {
        return $this->valid_to->isPast();
    }
}
