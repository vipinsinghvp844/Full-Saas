<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'class_id',
        'member_id',
        'schedule_id',
        'booking_date',
        'status', // booked, cancelled, attended
    ];

    protected $casts = [
        'booking_date' => 'date',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function gymClass()
    {
        return $this->belongsTo(GymClass::class, 'class_id');
    }

    public function schedule()
    {
        return $this->belongsTo(ClassSchedule::class, 'schedule_id');
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
