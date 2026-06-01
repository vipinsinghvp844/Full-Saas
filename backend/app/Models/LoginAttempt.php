<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LoginAttempt extends Model
{
    protected $fillable = [
        'email',
        'ip_address',
        'successful',
        'attempted_at',
        'locked_until',
    ];

    protected $casts = [
        'successful'   => 'boolean',
        'attempted_at' => 'datetime',
        'locked_until' => 'datetime',
    ];

    /**
     * Check if an email is currently locked out.
     */
    public static function isLocked(string $email): bool
    {
        return static::where('email', $email)
            ->where('locked_until', '>', Carbon::now())
            ->exists();
    }

    /**
     * Get the remaining lockout time in seconds (0 if not locked).
     */
    public static function lockoutRemainingSeconds(string $email): int
    {
        $record = static::where('email', $email)
            ->where('locked_until', '>', Carbon::now())
            ->orderByDesc('locked_until')
            ->first();

        if (! $record) return 0;

        return max(0, (int) Carbon::now()->diffInSeconds($record->locked_until, false) * -1);
    }

    /**
     * Count recent failed attempts within the last hour.
     */
    public static function recentFailedCount(string $email): int
    {
        return static::where('email', $email)
            ->where('successful', false)
            ->where('attempted_at', '>=', Carbon::now()->subHour())
            ->count();
    }

    /**
     * Lock the account for a given number of minutes.
     */
    public static function lockAccount(string $email, int $lockoutMinutes): void
    {
        static::where('email', $email)->update([
            'locked_until' => Carbon::now()->addMinutes($lockoutMinutes),
        ]);
    }

    /**
     * Clear lock for an email (e.g., on successful login).
     */
    public static function clearLock(string $email): void
    {
        static::where('email', $email)->update(['locked_until' => null]);
    }
}
