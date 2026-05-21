<?php

namespace App\Http\Controllers;

use App\Models\PasswordResetToken;
use App\Models\RefreshToken;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\JwtService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Str;

class AuthController extends ApiController
{
    public function login(Request $request)
    {
        try {
            $data = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ]);

            $user = User::with('roles')->where('email', $data['email'])->first();

            if (! $user || ! Hash::check($data['password'], $user->password)) {
                return $this->jsonResponse(['message' => 'The provided credentials are incorrect.'], 401, $request);
            }

            $refreshToken = JwtService::createRefreshToken($user);
            $payload = $this->buildTokenPayload($user, $refreshToken);

            return $this->jsonResponse($payload, 200, $request);
        } catch (\Throwable $e) {
            \Log::error('Login error: ' . $e->getMessage());
            return $this->jsonResponse(['message' => 'Login failed: ' . $e->getMessage()], 500, $request);
        }
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'gym_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $user = \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
                $slug = \Illuminate\Support\Str::slug($data['gym_name']);
                $baseSlug = $slug !== '' ? $slug : \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
                $slug = $baseSlug;
                $counter = 1;

                while (Tenant::where('slug', $slug)->exists()) {
                    $slug = "$baseSlug-$counter";
                    $counter++;
                }

                $tenant = Tenant::create([
                    'name' => $data['gym_name'],
                    'slug' => $slug,
                    'email' => $data['email'],
                    'status' => 'active',
                ]);

                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                ]);

                $tenant->update(['owner_user_id' => $user->id]);

                foreach (['Gym Admin', 'Manager', 'Trainer', 'Receptionist', 'Accountant'] as $roleName) {
                    Role::updateOrCreate(
                        [
                            'tenant_id' => $tenant->id,
                            'name' => $roleName,
                            'guard_name' => 'web',
                        ]
                    );
                }

                $role = Role::where('tenant_id', $tenant->id)
                    ->where('name', 'Gym Admin')
                    ->first();

                if ($role) {
                    $user->roles()->syncWithoutDetaching([
                        $role->id => ['tenant_id' => $tenant->id],
                    ]);
                }

                return $user;
            });

            $refreshToken = JwtService::createRefreshToken($user);
            $payload = $this->buildTokenPayload($user, $refreshToken);

            return $this->jsonResponse(array_merge([
                'message' => 'Registration successful. Welcome to the platform.',
            ], $payload), 201, $request);

        } catch (\Throwable $e) {
            \Log::error('Registration error: ' . $e->getMessage());
            return $this->jsonResponse(['message' => 'Registration failed: ' . $e->getMessage()], 500, $request);
        }
    }

    public function logout(Request $request)
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $hashedToken = hash('sha256', $data['refresh_token']);
        $token = RefreshToken::where('token', $hashedToken)->first();

        if ($token) {
            $token->update(['revoked_at' => Carbon::now()]);
        }

        return $this->jsonResponse(['message' => 'Logout successful.'], 200, $request);
    }

    public function refresh(Request $request)
    {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $hashedToken = hash('sha256', $data['refresh_token']);
        $storedToken = RefreshToken::where('token', $hashedToken)->first();

        if (! $storedToken || $storedToken->isRevoked() || $storedToken->isExpired()) {
            return $this->jsonResponse(['message' => 'Refresh token is invalid or expired.'], 401, $request);
        }

        $storedToken->update(['revoked_at' => Carbon::now()]);

        $user = $storedToken->user;
        $refreshToken = JwtService::createRefreshToken($user);
        $payload = $this->buildTokenPayload($user, $refreshToken);

        return $this->jsonResponse($payload, 200, $request);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            $token = Str::random(64);
            PasswordResetToken::updateOrCreate(
                ['email' => $user->email],
                [
                    'token' => hash('sha256', $token),
                    'expires_at' => Carbon::now()->addMinutes(config('auth.passwords.users.expire', 60)),
                ]
            );

            return $this->jsonResponse([
                'message' => 'Password reset token created successfully.',
                'reset_token' => $token,
            ], 200, $request);
        }

        return $this->jsonResponse(['message' => 'If the email exists, a reset link has been generated.'], 200, $request);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = PasswordResetToken::where('email', $data['email'])->first();

        if (! $record || $record->expires_at->isPast() || ! hash_equals($record->token, hash('sha256', $data['token']))) {
            return $this->jsonResponse(['message' => 'The password reset token is invalid or has expired.'], 400, $request);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user) {
            return $this->jsonResponse(['message' => 'User not found.'], 404, $request);
        }

        $user->update(['password' => Hash::make($data['password'])]);
        $record->delete();

        return $this->jsonResponse(['message' => 'Password has been reset successfully.'], 200, $request);
    }

    public function verifyEmail(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        try {
            $payload = JwtService::decodeEmailVerificationToken($data['token']);
        } catch (\Throwable $exception) {
            return $this->jsonResponse(['message' => 'Invalid or expired verification token.'], 400, $request);
        }

        $user = User::find($payload->sub);

        if (! $user) {
            return $this->jsonResponse(['message' => 'User not found.'], 404, $request);
        }

        $user->update(['email_verified_at' => Carbon::now()]);

        return $this->jsonResponse(['message' => 'Email verified successfully.'], 200, $request);
    }

    protected function buildTokenPayload(User $user, string $refreshToken): array
    {
        $user->loadMissing('roles');
        $accessToken = JwtService::generateAccessToken($user);
        $role = $this->resolvePrimaryRole($user);

        return [
            'access_token' => $accessToken,
            'token_type' => 'bearer',
            'expires_in' => JwtService::accessTtl(),
            'refresh_token' => $refreshToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
                'roles' => $user->roles->pluck('name')->toArray(),
            ],
            'redirect_to' => $this->resolveRedirect($role),
        ];
    }

    protected function resolvePrimaryRole(User $user): ?string
    {
        $roles = $user->roles->pluck('name');

        if ($user->tenant_id === null) {
            $platformRole = $roles->first(
                fn (string $name) => strtolower($name) === 'super admin'
            );

            if ($platformRole) {
                return $platformRole;
            }
        }

        return $roles->first();
    }

    protected function resolveRedirect(?string $role): string
    {
        return match (strtolower((string) $role)) {
            'super admin' => '/super-admin/dashboard',
            'gym admin' => '/gym/dashboard',
            'trainer' => '/trainer/dashboard',
            'manager', 'receptionist', 'accountant' => '/staff/dashboard',
            default => '/dashboard',
        };
    }
}

