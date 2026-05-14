<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'class_id',
        'day_of_week',
        'start_time',
        'end_time',
        'room',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function gymClass()
    {
        return $this->belongsTo(GymClass::class, 'class_id');
    }

    public function bookings()
    {
        return $this->hasMany(ClassBooking::class, 'schedule_id');
    }
}
