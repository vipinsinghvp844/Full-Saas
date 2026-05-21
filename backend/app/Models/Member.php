<?php

namespace App\Models;

use App\Models\MemberMembership;
use App\Models\Trainer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'assigned_trainer_id',
        'phone',
        'gender',
        'date_of_birth',
        'address',
        'emergency_contact',
        'joining_date',
        'status',
        'profile_picture',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'joining_date' => 'date',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTrainer()
    {
        return $this->belongsTo(Trainer::class, 'assigned_trainer_id');
    }

    public function activeMembership()
    {
        return $this->hasOne(MemberMembership::class, 'member_id')
            ->where('status', 'active')
            ->latestOfMany('end_date');
    }

    public function memberships()
    {
        return $this->hasMany(MemberMembership::class, 'member_id');
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class, 'member_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'member_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'member_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id', 'user_id');
    }
}
