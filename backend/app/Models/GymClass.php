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
        'max_participants',
        'duration_minutes',
        'trainer_id',
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
}
