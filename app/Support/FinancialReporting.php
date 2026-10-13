<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialReporting
{
    public function completedOrders(Carbon $start, Carbon $end): Builder
    {
        return DB::table('orders')->where('status', 'completed')->whereBetween('paid_at', [$start, $end]);
    }

    public function barTransactions(Carbon $start, Carbon $end): Builder
    {
        return DB::table('bar_transactions')->whereBetween('occurred_at', [$start, $end]);
    }

    public function cancellations(Carbon $start, Carbon $end): Builder
    {
        return DB::table('orders')->whereIn('status', ['cancelled', 'cancellation_pending'])->whereBetween('cancellation_requested_at', [$start, $end]);
    }

    /** @return array{income: int, order_income: int, bar_income: int, expenses: int, profit: int, payments_by_method: Collection<int, object>, expenses_by_category: Collection<int, object>, daily_trends: Collection<int, object>, recent_orders: Collection<int, object>, recent_bar_transactions: Collection<int, object>, cancellations: Collection<int, object>} */
    public function report(Carbon $periodStart, Carbon $periodEnd): array
    {
        $completedOrders = $this->completedOrders($periodStart, $periodEnd);
        $barTransactions = $this->barTransactions($periodStart, $periodEnd);
        $cancellationOrders = $this->cancellations($periodStart, $periodEnd);
        $barIncome = (int) (clone $barTransactions)->where('type', 'income')->sum('amount');
        $income = (int) (clone $completedOrders)->sum('total_amount') + $barIncome;
        $expenses = (int) (clone $barTransactions)->where('type', 'expense')->sum('amount');
        $recentBarTransactions = (clone $barTransactions)
            ->leftJoin('staff_members', 'bar_transactions.staff_member_id', '=', 'staff_members.id')
            ->select('bar_transactions.*', 'staff_members.name as staff_name')
            ->orderByDesc('bar_transactions.occurred_at')
            ->limit(20)
            ->get();
        $expensesByCategory = (clone $barTransactions)
            ->where('type', 'expense')
            ->select('category', DB::raw('COUNT(*) as transaction_count'), DB::raw('SUM(amount) as total_amount'))
            ->groupBy('category')
            ->orderBy('category')
            ->get();
        $paymentsByMethod = (clone $completedOrders)
            ->select('payment_method', DB::raw('COUNT(*) as transaction_count'), DB::raw('SUM(total_amount) as total_amount'))
            ->groupBy('payment_method')
            ->orderBy('payment_method')
            ->get();
        $dailyOrderTrends = (clone $completedOrders)
            ->selectRaw('DATE(paid_at) as payment_date, SUM(total_amount) as income_amount, 0 as expense_amount')
            ->groupByRaw('DATE(paid_at)');
        $dailyBarTrends = (clone $barTransactions)
            ->selectRaw("DATE(occurred_at) as payment_date, SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income_amount, SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense_amount")
            ->groupByRaw('DATE(occurred_at)');
        $dailyTrends = $dailyOrderTrends->unionAll($dailyBarTrends)->get()
            ->groupBy('payment_date')
            ->map(function (Collection $dailyRows, string $paymentDate): object {
                $dailyIncome = (int) $dailyRows->sum('income_amount');
                $dailyExpenses = (int) $dailyRows->sum('expense_amount');

                return (object) [
                    'payment_date' => $paymentDate,
                    'income_amount' => $dailyIncome,
                    'expense_amount' => $dailyExpenses,
                    'net_amount' => $dailyIncome - $dailyExpenses,
                ];
            })
            ->sortBy('payment_date')
            ->values();
        $recentTransactions = (clone $completedOrders)
            ->select('id', 'waiter_name', 'customer_name', 'status', 'total_amount', 'payment_method', 'paid_at')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'income' => $income, 'order_income' => $income - $barIncome, 'bar_income' => $barIncome, 'expenses' => $expenses, 'profit' => $income - $expenses,
            'payments_by_method' => $paymentsByMethod, 'expenses_by_category' => $expensesByCategory, 'daily_trends' => $dailyTrends,
            'recent_orders' => $recentTransactions, 'recent_bar_transactions' => $recentBarTransactions,
            'cancellations' => $cancellationOrders->select('id', 'waiter_name', 'customer_name', 'status', 'cancellation_reason', 'cancellation_requested_at')->orderByDesc('cancellation_requested_at')->orderByDesc('id')->limit(50)->get(),
        ];
    }

    public function exportRows(Carbon $periodStart, Carbon $periodEnd): Builder
    {
        $completedOrders = $this->completedOrders($periodStart, $periodEnd);
        $barTransactions = $this->barTransactions($periodStart, $periodEnd);
        $cancellationOrders = $this->cancellations($periodStart, $periodEnd);
        $orderExportRows = (clone $completedOrders)
            ->selectRaw("'Pemasukan' as transaction_type, id as reference, paid_at as happened_at, customer_name as description, waiter_name as staff_name, payment_method as category, total_amount as amount, 'Pembayaran order' as notes, status");
        $barExportRows = (clone $barTransactions)
            ->leftJoin('staff_members', 'bar_transactions.staff_member_id', '=', 'staff_members.id')
            ->selectRaw("CASE WHEN bar_transactions.type = 'income' THEN 'Pemasukan' ELSE 'Pengeluaran' END as transaction_type, bar_transactions.id as reference, bar_transactions.occurred_at as happened_at, bar_transactions.description, staff_members.name as staff_name, bar_transactions.category, bar_transactions.amount, bar_transactions.notes, bar_transactions.type as status");
        $cancellationExportRows = (clone $cancellationOrders)
            ->selectRaw("'Pembatalan' as transaction_type, id as reference, cancellation_requested_at as happened_at, customer_name as description, waiter_name as staff_name, '' as category, 0 as amount, cancellation_reason as notes, status");

        return $orderExportRows
            ->unionAll($barExportRows)
            ->unionAll($cancellationExportRows)
            ->orderBy('happened_at')
            ->orderBy('reference');

    }

    /** @return array{total_orders: int, income: int, active_orders: int, pending_cancellations: int, recent_orders: Collection<int, object>} */
    public function dashboard(int $recentLimit = 10): array
    {
        return [
            'total_orders' => DB::table('orders')->count(),
            'income' => (int) DB::table('orders')->where('status', 'completed')->sum('total_amount') + (int) DB::table('bar_transactions')->where('type', 'income')->sum('amount'),
            'active_orders' => DB::table('orders')->where('status', 'sent_to_kitchen')->count(),
            'pending_cancellations' => DB::table('orders')->where('status', 'cancellation_pending')->count(),
            'recent_orders' => DB::table('orders')->select('id', 'waiter_name', 'customer_name', 'status', 'total_amount', 'payment_method', 'paid_at', 'created_at')->orderByDesc('created_at')->orderByDesc('id')->limit($recentLimit)->get(),
        ];
    }
}
