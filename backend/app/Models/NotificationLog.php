<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'notification_id',
        'channel',
        'recipient',
        'event_type',
        'payload',
        'status',
        'error_message',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}
