<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayrollController extends ApiController
{
    // ── Dashboard ──────────────────────────────────────────────────────────────

    public function dashboard(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $now = Carbon::now();

        $monthPayrolls = Payroll::where('tenant_id', $tenantId)
            ->where('month', $now->month)
            ->where('year', $now->year);

        $totalThisMonth  = (float) (clone $monthPayrolls)->sum('net_salary');
        $paidThisMonth   = (float) (clone $monthPayrolls)->where('status', 'paid')->sum('net_salary');
        $pendingThisMonth = (float) (clone $monthPayrolls)->where('status', 'pending')->sum('net_salary');
        $pendingCount    = (clone $monthPayrolls)->where('status', 'pending')->count();
        $paidCount       = (clone $monthPayrolls)->where('status', 'paid')->count();

        $recentPayrolls = Payroll::with(['employee.user'])
            ->where('tenant_id', $tenantId)
            ->orderByDesc('year')->orderByDesc('month')
            ->limit(8)->get();

        return $this->jsonResponse([
            'data' => [
                'total_this_month'   => $totalThisMonth,
                'paid_this_month'    => $paidThisMonth,
                'pending_this_month' => $pendingThisMonth,
                'pending_count'      => $pendingCount,
                'paid_count'         => $paidCount,
                'recent_payrolls'    => $recentPayrolls,
            ],
        ]);
    }

    // ── List ───────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'month'       => ['nullable', 'integer', 'min:1', 'max:12'],
            'year'        => ['nullable', 'integer', 'min:2020'],
            'status'      => ['nullable', Rule::in(['pending', 'paid'])],
            'employee_id' => ['nullable', 'integer'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Payroll::with(['employee.user', 'employee.branch'])
            ->where('tenant_id', $tenantId);

        if (!empty($data['month']))       $query->where('month', $data['month']);
        if (!empty($data['year']))        $query->where('year', $data['year']);
        if (!empty($data['status']))      $query->where('status', $data['status']);
        if (!empty($data['employee_id'])) $query->where('employee_id', $data['employee_id']);

        $paginator = $query->orderByDesc('year')->orderByDesc('month')
            ->orderBy('employee_id')
            ->paginate((int) ($data['per_page'] ?? 20));

        return $this->jsonResponse([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ], 200, $request);
    }

    // ── Generate Monthly Payroll ───────────────────────────────────────────────

    public function generate(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year'  => ['required', 'integer', 'min:2020'],
        ]);

        $month = (int) $data['month'];
        $year  = (int) $data['year'];

        $employees = Employee::where('tenant_id', $tenantId)
            ->whereIn('status', ['active'])
            ->get();

        if ($employees->isEmpty()) {
            return $this->jsonResponse(['error' => 'No active employees found.'], 422);
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($employees, $tenantId, $month, $year, &$created, &$skipped) {
            $periodStart = Carbon::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
            $periodEnd   = Carbon::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

            foreach ($employees as $employee) {
                $exists = Payroll::where('tenant_id', $tenantId)
                    ->where('employee_id', $employee->id)
                    ->where('month', $month)
                    ->where('year', $year)
                    ->exists();

                if ($exists) { $skipped++; continue; }

                $baseSalary = (float) ($employee->salary ?? 0);
                $grossSalary = $baseSalary; // bonuses added later per record

                Payroll::create([
                    'tenant_id'    => $tenantId,
                    'employee_id'  => $employee->id,
                    'month'        => $month,
                    'year'         => $year,
                    'base_salary'  => $baseSalary,
                    'bonuses'      => 0,
                    'deductions'   => 0,
                    'gross_salary' => $grossSalary,
                    'net_salary'   => $grossSalary,
                    'period_start' => $periodStart,
                    'period_end'   => $periodEnd,
                    'status'       => 'pending',
                ]);

                $created++;
            }
        });

        return $this->jsonResponse([
            'message' => "Payroll generated: {$created} created, {$skipped} already existed.",
            'data'    => ['created' => $created, 'skipped' => $skipped],
        ], 201);
    }

    // ── Update (adjust bonuses/deductions) ────────────────────────────────────

    public function update(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $payroll  = Payroll::where('tenant_id', $tenantId)->findOrFail($id);

        if ($payroll->status === 'paid') {
            return $this->jsonResponse(['error' => 'Cannot modify a paid payroll.'], 422);
        }

        $data = $request->validate([
            'bonuses'    => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        $bonuses    = isset($data['bonuses'])    ? (float) $data['bonuses']    : (float) $payroll->bonuses;
        $deductions = isset($data['deductions']) ? (float) $data['deductions'] : (float) $payroll->deductions;
        $base       = (float) $payroll->base_salary;
        $net        = $base + $bonuses - $deductions;

        $payroll->update([
            'bonuses'      => $bonuses,
            'deductions'   => $deductions,
            'gross_salary' => $base + $bonuses,
            'net_salary'   => max(0, $net),
            'notes'        => $data['notes'] ?? $payroll->notes,
        ]);

        return $this->jsonResponse(['message' => 'Payroll updated.', 'data' => $payroll->fresh('employee.user')]);
    }

    // ── Mark as Paid (+ auto-create expense) ──────────────────────────────────

    public function markPaid(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $payroll  = Payroll::with('employee.user')->where('tenant_id', $tenantId)->findOrFail($id);

        if ($payroll->status === 'paid') {
            return $this->jsonResponse(['error' => 'Payroll is already marked as paid.'], 422);
        }

        DB::transaction(function () use ($payroll, $tenantId, $request) {
            $payroll->update([
                'status'  => 'paid',
                'paid_at' => now(),
            ]);

            // Auto-create expense under "Salary" category
            $salaryCategory = ExpenseCategory::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => 'Salary'],
                ['description' => 'Staff salary payments']
            );

            $monthName = Carbon::createFromDate($payroll->year, $payroll->month, 1)->format('F Y');
            $empName   = $payroll->employee->user->name ?? "Employee #{$payroll->employee_id}";

            $expense = Expense::create([
                'tenant_id'           => $tenantId,
                'expense_category_id' => $salaryCategory->id,
                'amount'              => $payroll->net_salary,
                'expense_date'        => now()->toDateString(),
                'payment_method'      => 'bank',
                'description'         => "Salary: {$empName} ({$monthName})",
                'recorded_by'         => $request->user()->id,
            ]);

            $payroll->update(['expense_id' => $expense->id]);
        });

        return $this->jsonResponse([
            'message' => 'Payroll marked as paid and expense recorded.',
            'data'    => $payroll->fresh('employee.user'),
        ]);
    }

    // ── Delete (only pending) ─────────────────────────────────────────────────

    public function destroy(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $payroll  = Payroll::where('tenant_id', $tenantId)->findOrFail($id);

        if ($payroll->status === 'paid') {
            return $this->jsonResponse(['error' => 'Cannot delete a paid payroll record.'], 422);
        }

        $payroll->delete();
        return $this->jsonResponse(['message' => 'Payroll deleted.']);
    }
}
