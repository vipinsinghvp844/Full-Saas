<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'plan_type',
        'price',
        'duration',
        'discount',
        'max_members',
        'max_trainers',
        'max_branches',
        'features',
        'status',
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    public function subscriptions()
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }
}
