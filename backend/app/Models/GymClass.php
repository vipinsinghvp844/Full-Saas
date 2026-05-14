<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GymClass extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'category',
        'capacity',
        'duration',
        'trainer_id',
        'status',
        'created_by',
        'updated_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function schedules()
    {
        return $this->hasMany(ClassSchedule::class, 'class_id');
    }

    public function bookings()
    {
        return $this->hasMany(ClassBooking::class, 'class_id');
    }
}
