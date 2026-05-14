<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'member_id',
        'membership_id',
        'invoice_id',
        'amount',
        'discount',
        'final_amount',
        'payment_method',
        'transaction_id',
        'status',
        'payment_status',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function membership()
    {
        return $this->belongsTo(MemberMembership::class, 'membership_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
