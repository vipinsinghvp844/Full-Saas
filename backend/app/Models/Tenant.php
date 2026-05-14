<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo_path',
        'slug',
        'email',
        'owner_user_id',
        'phone',
        'website',
        'address',
        'city',
        'state',
        'country',
        'zip',
        'gst_number',
        'status',
        'description',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return url($this->logo_path);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(TenantSubscription::class)
            ->where('status', 'active')
            ->latestOfMany('end_date');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function members()
    {
        return $this->hasMany(Member::class);
    }

    public function trainers()
    {
        return $this->hasMany(Trainer::class);
    }

    public function coupons()
    {
        return $this->hasMany(Coupon::class);
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    public function gymSettings()
    {
        return $this->hasMany(GymSetting::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }
}
