<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainerMember extends Model
{
    use HasFactory;

    protected $table = 'trainer_members';

    protected $fillable = [
        'tenant_id',
        'trainer_id',
        'member_id',
        'assigned_date',
    ];

    protected $casts = [
        'assigned_date' => 'date',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function trainer()
    {
        return $this->belongsTo(Trainer::class);
    }

    public function member()
    {
        return $this->belongsTo(Member::class);
    }
}
