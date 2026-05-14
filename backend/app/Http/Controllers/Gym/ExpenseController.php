<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\ApiController;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends ApiController
{
    // ─── Categories ─────────────────────────────────────────────────────────────

    public function categoryIndex(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $categories = ExpenseCategory::where('tenant_id', $tenantId)
            ->withCount('expenses')
            ->orderBy('name')
            ->get();

        return $this->jsonResponse(['data' => $categories]);
    }

    public function categoryStore(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100',
                              Rule::unique('expense_categories')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $data['tenant_id'] = $tenantId;
        $category = ExpenseCategory::create($data);

        return $this->jsonResponse(['message' => 'Category created.', 'data' => $category], 201);
    }

    public function categoryUpdate(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $category = ExpenseCategory::where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:100',
                              Rule::unique('expense_categories')->where('tenant_id', $tenantId)->ignore($id)],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $category->update($data);

        return $this->jsonResponse(['message' => 'Category updated.', 'data' => $category]);
    }

    public function categoryDestroy(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $category = ExpenseCategory::where('tenant_id', $tenantId)->findOrFail($id);

        // Prevent deleting if expenses exist
        if ($category->expenses()->count() > 0) {
            return $this->jsonResponse(['error' => 'Cannot delete a category that has expenses.'], 422);
        }

        $category->delete();

        return $this->jsonResponse(['message' => 'Category deleted.']);
    }

    // ─── Default Category Seeder ────────────────────────────────────────────────

    public function seedDefaultCategories(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $defaults = ['Rent', 'Electricity', 'Salary', 'Maintenance', 'Marketing', 'Equipment', 'Cleaning', 'Internet'];

        foreach ($defaults as $name) {
            ExpenseCategory::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $name],
                ['description' => "Default category: {$name}"]
            );
        }

        return $this->jsonResponse(['message' => 'Default categories seeded.']);
    }

    // ─── Expenses ────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'category_id' => ['nullable', 'integer', 'exists:expense_categories,id'],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank', 'UPI'])],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Expense::with('category', 'recordedBy')
            ->where('tenant_id', $tenantId);

        if (!empty($data['start_date'])) {
            $query->whereDate('expense_date', '>=', $data['start_date']);
        }
        if (!empty($data['end_date'])) {
            $query->whereDate('expense_date', '<=', $data['end_date']);
        }
        if (!empty($data['category_id'])) {
            $query->where('expense_category_id', $data['category_id']);
        }
        if (!empty($data['payment_method'])) {
            $query->where('payment_method', $data['payment_method']);
        }

        $paginator = $query->orderByDesc('expense_date')->orderByDesc('id')
            ->paginate((int) ($data['per_page'] ?? 20));

        return $this->jsonResponse([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
        ], 200, $request);
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'expense_date'        => ['required', 'date'],
            'payment_method'      => ['required', Rule::in(['cash', 'bank', 'UPI'])],
            'description'         => ['nullable', 'string', 'max:1000'],
        ]);

        // Ensure category belongs to tenant
        ExpenseCategory::where('tenant_id', $tenantId)->findOrFail($data['expense_category_id']);

        $data['tenant_id']  = $tenantId;
        $data['recorded_by'] = $request->user()->id;

        $expense = Expense::create($data);
        $expense->load('category', 'recordedBy');

        return $this->jsonResponse(['message' => 'Expense recorded.', 'data' => $expense], 201);
    }

    public function update(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $expense  = Expense::where('tenant_id', $tenantId)->findOrFail($id);

        $data = $request->validate([
            'expense_category_id' => ['sometimes', 'integer', 'exists:expense_categories,id'],
            'amount'              => ['sometimes', 'numeric', 'min:0.01'],
            'expense_date'        => ['sometimes', 'date'],
            'payment_method'      => ['sometimes', Rule::in(['cash', 'bank', 'UPI'])],
            'description'         => ['nullable', 'string', 'max:1000'],
        ]);

        $expense->update($data);
        $expense->load('category');

        return $this->jsonResponse(['message' => 'Expense updated.', 'data' => $expense]);
    }

    public function destroy(Request $request, $id)
    {
        $tenantId = $request->user()->tenant_id;
        $expense  = Expense::where('tenant_id', $tenantId)->findOrFail($id);
        $expense->delete();

        return $this->jsonResponse(['message' => 'Expense deleted.']);
    }

    // ─── Dashboard / Analytics ───────────────────────────────────────────────────

    public function dashboard(Request $request)
    {
        $tenantId = $request->user()->tenant_id;
        $today    = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();

        // Total expenses
        $totalExpensesAll   = (float) Expense::where('tenant_id', $tenantId)->sum('amount');
        $todayExpenses      = (float) Expense::where('tenant_id', $tenantId)->whereDate('expense_date', $today)->sum('amount');
        $monthExpenses      = (float) Expense::where('tenant_id', $tenantId)->whereBetween('expense_date', [$startOfMonth, $today])->sum('amount');

        // Revenue (paid payments this month)
        $monthRevenue = (float) Payment::where('tenant_id', $tenantId)
            ->where('payment_status', 'paid')
            ->whereBetween('paid_at', [$startOfMonth->startOfDay(), $today->copy()->endOfDay()])
            ->sum('final_amount');

        $profit = round($monthRevenue - $monthExpenses, 2);

        // Category breakdown (this month)
        $categoryBreakdown = Expense::with('category')
            ->where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$startOfMonth, $today])
            ->selectRaw('expense_category_id, SUM(amount) as total')
            ->groupBy('expense_category_id')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category->name ?? 'Unknown',
                'total'    => (float) $row->total,
            ]);

        // Payment method breakdown (this month)
        $methodBreakdown = Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$startOfMonth, $today])
            ->selectRaw('payment_method, SUM(amount) as total')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->payment_method,
                'total'  => (float) $row->total,
            ]);

        // Expense trend (last 30 days)
        $thirtyDaysAgo = $today->copy()->subDays(29);
        $trendDB = Expense::where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$thirtyDaysAgo, $today])
            ->selectRaw('DATE(expense_date) as date, SUM(amount) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $trend = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = $today->copy()->subDays($i)->format('Y-m-d');
            $trend[] = [
                'label' => Carbon::parse($d)->format('d M'),
                'total' => (float) ($trendDB[$d] ?? 0),
            ];
        }

        // Recent expenses
        $recentExpenses = Expense::with('category')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return $this->jsonResponse([
            'data' => [
                'today_expenses'     => $todayExpenses,
                'month_expenses'     => $monthExpenses,
                'total_expenses_all' => $totalExpensesAll,
                'month_revenue'      => $monthRevenue,
                'profit'             => $profit,
                'category_breakdown' => $categoryBreakdown,
                'method_breakdown'   => $methodBreakdown,
                'trend'              => $trend,
                'recent_expenses'    => $recentExpenses,
            ],
        ]);
    }
}
