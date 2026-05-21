<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Models\GymSetting;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends ApiController
{
    /**
     * The mask placeholder returned to the frontend for secret keys.
     * If the frontend sends this back on save, we skip the field.
     */
    const SECRET_MASK = '••••••••';

    /**
     * Keys that are considered sensitive and must be masked before sending
     * to the frontend. Real values stay server-side only.
     */
    const SECRET_KEYS = [
        'stripe_secret_key',
        'razorpay_secret',
        'webhook_secret',
    ];

    // ── Profile Settings ────────────────────────────────────────────────────────

    public function getProfile(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $tenant = Tenant::findOrFail($tenantId);

        return $this->jsonResponse(['data' => $tenant]);
    }

    public function updateProfile(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $tenant = Tenant::findOrFail($tenantId);

        $data = $request->validate([
            'name'                   => ['sometimes', 'string', 'max:255'],
            'email'                  => ['sometimes', 'email', 'max:255'],
            'phone'                  => ['sometimes', 'string', 'max:20'],
            'website'                => ['nullable', 'string', 'max:255'],
            'address'                => ['nullable', 'string', 'max:255'],
            'city'                   => ['nullable', 'string', 'max:100'],
            'state'                  => ['nullable', 'string', 'max:100'],
            'country'                => ['nullable', 'string', 'max:100'],
            'zip'                    => ['nullable', 'string', 'max:20'],
            'description'            => ['nullable', 'string'],
            'website_enabled'        => ['nullable', 'boolean'],
            'website_template'       => ['nullable', 'string', 'max:50'],
            'seo_title'              => ['nullable', 'string', 'max:255'],
            'seo_description'        => ['nullable', 'string', 'max:1000'],
            'seo_keywords'           => ['nullable', 'string', 'max:555'],
            'custom_domain'          => ['nullable', 'string', 'max:255', Rule::unique('tenants')->ignore($tenant->id)],
            'custom_domain_verified' => ['nullable', 'boolean'],
            'opening_hours'          => ['nullable', 'array'],
            'social_links'           => ['nullable', 'array'],
            'banner_image'           => ['nullable', 'string', 'max:2048'],
            'trainers_data'          => ['nullable', 'array'],
            'pricing_plans'          => ['nullable', 'array'],
            'gallery_images'         => ['nullable', 'array'],
            'services'               => ['nullable', 'array'],
            'classes_data'           => ['nullable', 'array'],
            'blogs_data'             => ['nullable', 'array'],
            'latitude'               => ['nullable', 'numeric'],
            'longitude'              => ['nullable', 'numeric'],
            'header_data'            => ['nullable', 'array'],
            'footer_data'            => ['nullable', 'array'],
        ]);

        $tenant->update($data);

        return $this->jsonResponse(['message' => 'Profile updated successfully.', 'data' => $tenant]);
    }

    public function uploadLogo(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $tenant = Tenant::findOrFail($tenantId);

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,jpg,svg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($tenant->logo_path && Storage::disk('public')->exists(str_replace('storage/', '', $tenant->logo_path))) {
                Storage::disk('public')->delete(str_replace('storage/', '', $tenant->logo_path));
            }

            $path = $request->file('logo')->store('tenant_logos', 'public');
            $tenant->update(['logo_path' => 'storage/' . $path]);
        }

        return $this->jsonResponse(['message' => 'Logo uploaded successfully.', 'data' => $tenant]);
    }

    // ── Payment Gateway Settings ─────────────────────────────────────────────────
    //
    // Real-world approach:
    //   • Payment keys are stored in the same gym_settings KV table, scoped by tenant_id.
    //   • The UNIQUE(tenant_id, key) constraint guarantees exactly ONE row per key per gym
    //     no matter how many gyms are added — scales cleanly to thousands of tenants.
    //   • Secret keys (stripe_secret_key, razorpay_secret, webhook_secret) are NEVER
    //     returned to the frontend in plaintext. They are replaced with a mask string.
    //   • On save, if the frontend sends back the mask string, we skip that key entirely,
    //     preserving the real value already stored in the DB.
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Payment-specific keys we manage in this endpoint.
     */
    const PAYMENT_KEYS = [
        'payment_provider',
        'payment_mode',
        'stripe_public_key',
        'stripe_secret_key',
        'razorpay_key',
        'razorpay_secret',
        'webhook_secret',
    ];

    public function getPaymentSettings(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        // Fetch only payment-related rows — efficient single-tenant query
        $raw = GymSetting::where('tenant_id', $tenantId)
            ->whereIn('key', self::PAYMENT_KEYS)
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        // Build response with defaults, masking secrets
        $settings = [
            'payment_provider'  => $raw['payment_provider']  ?? 'offline',
            'payment_mode'      => $raw['payment_mode']       ?? 'test',
            'stripe_public_key' => $raw['stripe_public_key']  ?? '',
            'stripe_secret_key' => !empty($raw['stripe_secret_key']) ? self::SECRET_MASK : '',
            'razorpay_key'      => $raw['razorpay_key']       ?? '',
            'razorpay_secret'   => !empty($raw['razorpay_secret'])   ? self::SECRET_MASK : '',
            'webhook_secret'    => !empty($raw['webhook_secret'])     ? self::SECRET_MASK : '',
        ];

        // Derive a computed status flag for the frontend
        $settings['payments_enabled'] = $this->paymentsConfigured($tenantId, $raw['payment_provider'] ?? 'offline');

        return $this->jsonResponse(['data' => $settings]);
    }

    public function updatePaymentSettings(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'payment_provider'  => ['required', 'in:stripe,razorpay,offline'],
            'payment_mode'      => ['required', 'in:test,live'],
            'stripe_public_key' => ['nullable', 'string', 'max:255'],
            'stripe_secret_key' => ['nullable', 'string', 'max:255'],
            'razorpay_key'      => ['nullable', 'string', 'max:255'],
            'razorpay_secret'   => ['nullable', 'string', 'max:255'],
            'webhook_secret'    => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($tenantId, $data) {
            foreach ($data as $key => $value) {
                // If the frontend sends back our mask placeholder, skip the key —
                // it means the user didn't change the secret, so we keep the real value.
                if (in_array($key, self::SECRET_KEYS) && $value === self::SECRET_MASK) {
                    continue;
                }

                GymSetting::updateOrCreate(
                    ['tenant_id' => $tenantId, 'key' => $key],
                    ['value' => $value ?? '']
                );
            }
        });

        // Return masked settings after save
        return $this->getPaymentSettings($request);
    }

    /**
     * Check if the active payment provider has been properly configured with API keys.
     */
    private function paymentsConfigured(int $tenantId, string $provider): bool
    {
        if ($provider === 'offline') {
            return true; // Offline always works
        }

        if ($provider === 'stripe') {
            $count = GymSetting::where('tenant_id', $tenantId)
                ->whereIn('key', ['stripe_public_key', 'stripe_secret_key'])
                ->where('value', '!=', '')
                ->count();
            return $count === 2;
        }

        if ($provider === 'razorpay') {
            $count = GymSetting::where('tenant_id', $tenantId)
                ->whereIn('key', ['razorpay_key', 'razorpay_secret'])
                ->where('value', '!=', '')
                ->count();
            return $count === 2;
        }

        return false;
    }

    // ── General Key-Value Settings ───────────────────────────────────────────────
    //
    // Handles: Billing, Notifications, Advanced / Gym Rules, Localization.
    //
    // DB design note:
    //   Each gym gets at most ~25 KV rows for all general settings combined.
    //   The UNIQUE(tenant_id, key) index makes updateOrCreate a single efficient
    //   upsert per key. With 10,000 gyms → max 250,000 rows, still very fast.
    // ─────────────────────────────────────────────────────────────────────────────

    /** Keys that are allowed in the general KV endpoint (whitelist for security) */
    const ALLOWED_GENERAL_KEYS = [
        // Localization
        'currency_symbol', 'currency_code', 'timezone', 'date_format',
        // Billing
        'invoice_prefix', 'tax_percent', 'auto_renew', 'trial_days',
        // Notifications
        'enable_renewal_alerts', 'enable_payment_alerts', 'enable_email_alerts', 'enable_sms_alerts',
        // Gym Rules
        'default_class_capacity', 'max_members_limit', 'attendance_rules',
    ];

    public function getSettings(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        // Fetch only allowed keys — keeps the response lean
        $settings = GymSetting::where('tenant_id', $tenantId)
            ->whereIn('key', self::ALLOWED_GENERAL_KEYS)
            ->get()
            ->pluck('value', 'key');

        return $this->jsonResponse(['data' => $settings]);
    }

    public function updateSettings(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'settings'                          => ['required', 'array'],
            // Localization
            'settings.currency_symbol'          => ['nullable', 'string', 'max:10'],
            'settings.currency_code'            => ['nullable', 'string', 'max:10'],
            'settings.timezone'                 => ['nullable', 'string', 'max:100'],
            'settings.date_format'              => ['nullable', 'string', 'max:20'],
            // Billing
            'settings.invoice_prefix'           => ['nullable', 'string', 'max:20'],
            'settings.tax_percent'              => ['nullable', 'numeric', 'min:0', 'max:100'],
            'settings.auto_renew'               => ['nullable', 'in:true,false,0,1'],
            'settings.trial_days'               => ['nullable', 'integer', 'min:0', 'max:365'],
            // Notifications
            'settings.enable_renewal_alerts'    => ['nullable', 'in:true,false,0,1'],
            'settings.enable_payment_alerts'    => ['nullable', 'in:true,false,0,1'],
            'settings.enable_email_alerts'      => ['nullable', 'in:true,false,0,1'],
            'settings.enable_sms_alerts'        => ['nullable', 'in:true,false,0,1'],
            // Gym Rules
            'settings.default_class_capacity'   => ['nullable', 'integer', 'min:1'],
            'settings.max_members_limit'        => ['nullable', 'integer', 'min:1'],
            'settings.attendance_rules'         => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($tenantId, $data) {
            foreach ($data['settings'] as $key => $value) {
                // Only store whitelisted keys — reject unknown keys silently
                if (!in_array($key, self::ALLOWED_GENERAL_KEYS)) {
                    continue;
                }

                GymSetting::updateOrCreate(
                    ['tenant_id' => $tenantId, 'key' => $key],
                    ['value' => is_array($value) ? json_encode($value) : (string) $value]
                );
            }
        });

        // Return fresh settings after save
        $settings = GymSetting::where('tenant_id', $tenantId)
            ->whereIn('key', self::ALLOWED_GENERAL_KEYS)
            ->get()
            ->pluck('value', 'key');

        return $this->jsonResponse(['message' => 'Settings saved successfully.', 'data' => $settings]);
    }

    // ── User Management ──────────────────────────────────────────────────────────

    public function getUsers(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $users = User::with('roles')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get()
            ->map(function ($user) {
                return [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'email'     => $user->email,
                    'phone'     => $user->phone,
                    'is_active' => (bool) $user->is_active,
                    'roles'     => $user->roles->map(fn($r) => ['id' => $r->id, 'name' => $r->name]),
                ];
            });

        return $this->jsonResponse(['data' => $users]);
    }

    public function getRoles(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $roles = Role::whereNull('tenant_id')->orWhere('tenant_id', $tenantId)->get();

        return $this->jsonResponse(['data' => $roles]);
    }

    public function createUser(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', 'unique:users'],
            'phone'     => ['nullable', 'string', 'max:20'],
            'password'  => ['required', 'string', 'min:8', 'confirmed'],
            'roles'     => ['nullable', 'array'],
            'roles.*'   => ['exists:roles,name'],
            'is_active' => ['boolean'],
        ]);

        $user = DB::transaction(function () use ($data, $tenantId) {
            $user = User::create([
                'tenant_id' => $tenantId,
                'name'      => $data['name'],
                'email'     => $data['email'],
                'phone'     => $data['phone'] ?? null,
                'password'  => Hash::make($data['password']),
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (!empty($data['roles'])) {
                $roleIds = Role::whereIn('name', $data['roles'])->pluck('id')->toArray();
                $syncData = array_fill_keys($roleIds, ['tenant_id' => $tenantId]);
                $user->roles()->attach($syncData);
            }

            return $user;
        });

        return $this->jsonResponse([
            'message' => 'User created successfully.',
            'data'    => $user->load('roles'),
        ], 201);
    }

    public function updateUser(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $user = User::where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:255'],
            'email'     => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone'     => ['nullable', 'string', 'max:20'],
            'password'  => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles'     => ['nullable', 'array'],
            'roles.*'   => ['exists:roles,name'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($user, $data, $tenantId) {
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $user->update($data);

            if (isset($data['roles'])) {
                $roleIds = Role::whereIn('name', $data['roles'])->pluck('id')->toArray();
                $syncData = array_fill_keys($roleIds, ['tenant_id' => $tenantId]);
                $user->roles()->sync($syncData);
            }
        });

        return $this->jsonResponse([
            'message' => 'User updated successfully.',
            'data'    => $user->load('roles'),
        ]);
    }

    public function toggleUserStatus(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $user = User::where('tenant_id', $tenantId)->findOrFail($id);

        $user->update(['is_active' => !$user->is_active]);

        return $this->jsonResponse([
            'message'   => 'User status updated.',
            'is_active' => (bool) $user->is_active,
        ]);
    }

    public function uploadMedia(Request $request)
    {
        $request->validate([
            'file' => ['required', 'image', 'max:5120'], // 5MB max
        ]);

        $file = $request->file('file');
        $path = $file->store('cms', 'public');
        
        $url = asset('storage/' . $path);

        return $this->jsonResponse([
            'message' => 'File uploaded successfully',
            'url' => $url,
            'path' => $path
        ]);
    }
}

