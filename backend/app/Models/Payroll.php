<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'month',
        'year',
        'base_salary',
        'bonuses',
        'deductions',
        'net_salary',
        'gross_salary',
        'period_start',
        'period_end',
        'status',      // pending, paid
        'paid_at',
        'notes',
        'expense_id',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'bonuses'     => 'decimal:2',
        'deductions'  => 'decimal:2',
        'net_salary'  => 'decimal:2',
        'gross_salary'=> 'decimal:2',
        'paid_at'     => 'datetime',
        'period_start'=> 'date',
        'period_end'  => 'date',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
