<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'notifiable_type',
        'notifiable_id',
        'title',
        'message',
        'type',
        'category',
        'channel',
        'priority',
        'dedup_key',
        'data',
        'read',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /* ───── Relationships ───── */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function logs()
    {
        return $this->hasMany(NotificationLog::class);
    }

    /* ───── Scopes ───── */

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeUnread($query)
    {
        return $query->where('read', false);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}
