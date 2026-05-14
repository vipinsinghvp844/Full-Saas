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
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'zip' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
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

    // ── Key-Value Settings ───────────────────────────────────────────────────────

    public function getSettings(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $settings = GymSetting::where('tenant_id', $tenantId)->get()->pluck('value', 'key');

        return $this->jsonResponse(['data' => $settings]);
    }

    public function updateSettings(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        
        $data = $request->validate([
            'settings' => ['required', 'array'],
        ]);

        DB::transaction(function () use ($tenantId, $data) {
            foreach ($data['settings'] as $key => $value) {
                GymSetting::updateOrCreate(
                    ['tenant_id' => $tenantId, 'key' => $key],
                    ['value' => is_array($value) ? json_encode($value) : $value]
                );
            }
        });

        $settings = GymSetting::where('tenant_id', $tenantId)->get()->pluck('value', 'key');

        return $this->jsonResponse(['message' => 'Settings updated successfully.', 'data' => $settings]);
    }

    // ── User Management ──────────────────────────────────────────────────────────

    public function getUsers(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $users = User::with('roles')
            ->where('tenant_id', $tenantId)
            ->get();

        return $this->jsonResponse(['data' => $users]);
    }

    public function getRoles(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        // Optionally, scope roles by tenant if needed
        $roles = Role::whereNull('tenant_id')->orWhere('tenant_id', $tenantId)->get();

        return $this->jsonResponse(['data' => $roles]);
    }

    public function createUser(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
            'is_active' => ['boolean'],
        ]);

        $user = DB::transaction(function () use ($data, $tenantId) {
            $user = User::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (!empty($data['roles'])) {
                $roleIds = Role::whereIn('name', $data['roles'])->pluck('id')->toArray();
                $user->roles()->attach($roleIds, ['tenant_id' => $tenantId]);
            }

            return $user;
        });

        return $this->jsonResponse(['message' => 'User created successfully.', 'data' => $user->load('roles')], 201);
    }

    public function updateUser(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $user = User::where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
            'is_active' => ['boolean'],
        ]);

        DB::transaction(function () use ($user, $data, $tenantId) {
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }

            $user->update($data);

            if (isset($data['roles'])) {
                $roleIds = Role::whereIn('name', $data['roles'])->pluck('id')->toArray();
                // Sync roles, passing pivot data
                $syncData = [];
                foreach ($roleIds as $roleId) {
                    $syncData[$roleId] = ['tenant_id' => $tenantId];
                }
                $user->roles()->sync($syncData);
            }
        });

        return $this->jsonResponse(['message' => 'User updated successfully.', 'data' => $user->load('roles')]);
    }
}
