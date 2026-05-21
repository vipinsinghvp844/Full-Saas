<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
        'branch_id',
        'phone',
        'position',
        'hire_date',
        'avatar',
        'salary',
        'shift',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'hire_date' => 'date',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function trainer()
    {
        return $this->hasOne(Trainer::class);
    }
}
