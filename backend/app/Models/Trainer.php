<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trainer extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'tenant_id',
        'user_id',
        'specialization',
        'experience_years',
        'certifications',
        'bio',
        'avatar',

        // Extended operational fields (added by gym admin builds)
        'phone',
        'salary',
        'shift',
        'status',

        'created_by',
        'updated_by',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function classes()
    {
        return $this->hasMany(GymClass::class);
    }

    public function trainerMembers()
    {
        return $this->hasMany(TrainerMember::class, 'trainer_id');
    }

    public function assignedMembers()
    {
        return $this->hasMany(Member::class, 'assigned_trainer_id');
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class, 'trainer_id');
    }
}
