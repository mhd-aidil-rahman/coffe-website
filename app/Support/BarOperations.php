<?php

namespace App\Support;

use App\Models\StaffMember;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BarOperations
{
    public function __construct(private MenuCatalog $catalog, private OrderWorkflow $orders) {}

    public function periodStart(string $period): Carbon
    {
        return match ($period) {
            '7days' => now()->subDays(6)->startOfDay(),
            '30days' => now()->subDays(29)->startOfDay(),
            default => now()->startOfDay(),
        };
    }

    /** @param array{type?: string, category?: string, description?: string, amount?: int|string, notes?: ?string} $data */
    public function create(StaffMember $barista, array $data): object
    {
        $validated = Validator::make($data, [
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['required', Rule::in(['Penjualan', 'Operasional', 'Bahan Baku', 'Peralatan', 'Lainnya'])],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();
        $transactionId = DB::table('bar_transactions')->insertGetId([
            'staff_member_id' => $barista->id,
            ...$validated,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $transaction = DB::table('bar_transactions')
            ->leftJoin('staff_members', 'bar_transactions.staff_member_id', '=', 'staff_members.id')
            ->select('bar_transactions.*', 'staff_members.name as staff_name')
            ->where('bar_transactions.id', $transactionId)
            ->firstOrFail();

        return $transaction;
    }

    /** @return Collection<int, object> */
    public function transactions(Carbon $periodStart, ?int $limit = 50): Collection
    {
        $barTransactions = DB::table('bar_transactions')
            ->leftJoin('staff_members', 'bar_transactions.staff_member_id', '=', 'staff_members.id')
            ->select('bar_transactions.*', 'staff_members.name as staff_name')
            ->where('bar_transactions.occurred_at', '>=', $periodStart)
            ->orderByDesc('bar_transactions.occurred_at')
            ->when($limit !== null, fn (Builder $query): Builder => $query->limit($limit))
            ->get()
            ->map(function (object $transaction): object {
                $transaction->source = 'bar';

                return $transaction;
            });
        $paidOrders = DB::table('orders')
            ->where('status', 'completed')
            ->where('paid_at', '>=', $periodStart)
            ->orderByDesc('paid_at')
            ->when($limit !== null, fn (Builder $query): Builder => $query->limit($limit))
            ->get()
            ->map(fn (object $order): object => (object) [
                'id' => $order->id,
                'source' => 'order',
                'type' => 'income',
                'category' => 'Pembayaran '.strtoupper((string) $order->payment_method),
                'description' => 'Order #'.$order->id.($order->customer_name ? ' · '.$order->customer_name : ''),
                'amount' => $order->total_amount,
                'notes' => $order->waiter_name,
                'staff_name' => $order->waiter_name,
                'occurred_at' => $order->paid_at,
            ]);

        return $barTransactions
            ->concat($paidOrders)
            ->sortByDesc('occurred_at')
            ->values();
    }

    /** @return array{period: string, summary: array{income: int, expenses: int, profit: int}, incoming_orders: Collection<int, object>, active_menu_items: Collection<int, object>, transactions: Collection<int, object>} */
    public function operational(string $period, ?int $limit = 50): array
    {
        $periodStart = $this->periodStart($period);
        $transactions = $this->transactions($periodStart, $limit);
        $orderIncome = (int) DB::table('orders')
            ->where('status', 'completed')
            ->where('paid_at', '>=', $periodStart)
            ->sum('total_amount');
        $barIncome = (int) DB::table('bar_transactions')
            ->where('type', 'income')
            ->where('occurred_at', '>=', $periodStart)
            ->sum('amount');
        $income = $orderIncome + $barIncome;
        $expenses = (int) DB::table('bar_transactions')
            ->where('type', 'expense')
            ->where('occurred_at', '>=', $periodStart)
            ->sum('amount');

        $incomingOrders = $this->orders->withOrderItems(DB::table('orders')->whereIn('status', ['sent_to_kitchen', 'cancellation_pending'])->orderByDesc('created_at')->orderByDesc('id')->limit(50)->get());
        $menus = $this->catalog->query()->where('menu_items.active', true)->orderBy('menu_items.name')->orderBy('menu_items.id')->get();

        return ['period' => $period, 'summary' => ['income' => $income, 'expenses' => $expenses, 'profit' => $income - $expenses], 'incoming_orders' => $incomingOrders, 'active_menu_items' => $menus, 'transactions' => $transactions];
    }
}
