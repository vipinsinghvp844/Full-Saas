<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MemberMembership extends Model
{
    use HasFactory;

    protected $table = 'member_memberships';

    protected $fillable = [
        'tenant_id',
        'member_id',
        'plan_id',
        'start_date',
        'end_date',
        'status',
        'payment_status',
        'final_amount',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'final_amount' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function plan()
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'membership_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'membership_id');
    }
}
