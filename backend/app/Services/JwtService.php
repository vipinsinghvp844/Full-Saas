<?php

namespace App\Services;

use App\Models\RefreshToken;
use App\Models\User;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Str;

class JwtService
{
    public static function secret(): string
    {
        $secret = env('JWT_SECRET');

        if (!empty($secret)) {
            return $secret;
        }

        $key = config('app.key');

        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return $key;
    }

    public static function accessTtl(): int
    {
        return (int) env('JWT_TTL', 3600);
    }

    public static function refreshTtl(): int
    {
        return (int) env('JWT_REFRESH_TTL', 604800);
    }

    public static function generateAccessToken(User $user): string
    {
        $now = Carbon::now()->timestamp;

        $payload = [
            'sub' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'roles' => $user->roles->pluck('name')->toArray(),
            'iat' => $now,
            'exp' => $now + self::accessTtl(),
            'type' => 'access',
        ];

        return JWT::encode($payload, self::secret(), 'HS256');
    }

    public static function createRefreshToken(User $user): string
    {
        $plainText = Str::random(80);
        $hashedToken = hash('sha256', $plainText);

        RefreshToken::create([
            'user_id' => $user->id,
            'token' => $hashedToken,
            'expires_at' => Carbon::now()->addSeconds(self::refreshTtl()),
        ]);

        return $plainText;
    }

    public static function decodeToken(string $token): object
    {
        return JWT::decode($token, new Key(self::secret(), 'HS256'));
    }

    public static function createEmailVerificationToken(User $user): string
    {
        $now = Carbon::now()->timestamp;

        $payload = [
            'sub' => $user->id,
            'email' => $user->email,
            'type' => 'email_verification',
            'iat' => $now,
            'exp' => $now + 86400,
        ];

        return JWT::encode($payload, self::secret(), 'HS256');
    }

    public static function decodeEmailVerificationToken(string $token): object
    {
        $payload = self::decodeToken($token);

        if (! isset($payload->type) || $payload->type !== 'email_verification') {
            throw new \UnexpectedValueException('Invalid verification token.');
        }

        return $payload;
    }
}
