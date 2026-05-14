<?php

namespace App\Models;

use App\Models\Role;
use App\Models\Tenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['tenant_id', 'name', 'email', 'password', 'phone', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps()->withPivot('tenant_id');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains(function (Role $role) use ($roleName) {
            return strtolower($role->name) === strtolower($roleName);
        });
    }

    public function hasAnyRole(array $roleNames): bool
    {
        $allowedRoles = array_map('strtolower', $roleNames);

        return $this->roles->contains(function (Role $role) use ($allowedRoles) {
            return in_array(strtolower($role->name), $allowedRoles, true);
        });
    }

    public function hasPermission(string $permissionName): bool
    {
        return $this->roles->flatMap(function (Role $role) {
            return $role->permissions->pluck('name');
        })->contains($permissionName);
    }
}
