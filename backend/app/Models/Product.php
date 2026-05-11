<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'description',
        'price',
        'stock_quantity',
        'min_stock',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
