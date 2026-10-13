<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderNotifications
{
    /** @param array{after_paid_at?: string, after_order_id?: int|string} $data
     * @return Collection<int, array{id: int, customer_name: string, waiter_name: string, total_amount: int, payment_method: string, paid_at: string}> */
    public function completed(array $data): Collection
    {
        $validated = Validator::make($data, [
            'after_paid_at' => ['required', 'date'],
            'after_order_id' => ['required', 'integer', 'min:0'],
        ])->validate();
        $afterPaidAt = Carbon::parse($validated['after_paid_at'])
            ->setTimezone(config('app.timezone'))
            ->toDateTimeString();
        $afterOrderId = (int) $validated['after_order_id'];
        $events = DB::table('orders')
            ->select('id', 'customer_name', 'waiter_name', 'total_amount', 'payment_method', 'paid_at')
            ->where('status', 'completed')
            ->whereNotNull('paid_at')
            ->where(function (Builder $query) use ($afterPaidAt, $afterOrderId): void {
                $query->where('paid_at', '>', $afterPaidAt)
                    ->orWhere(function (Builder $query) use ($afterPaidAt, $afterOrderId): void {
                        $query->where('paid_at', $afterPaidAt)
                            ->where('id', '>', $afterOrderId);
                    });
            })
            ->orderBy('paid_at')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (object $order): array => [
                'id' => $order->id,
                'customer_name' => $order->customer_name ?: 'Pelanggan',
                'waiter_name' => $order->waiter_name,
                'total_amount' => (int) $order->total_amount,
                'payment_method' => $order->payment_method,
                'paid_at' => Carbon::parse($order->paid_at)->toIso8601String(),
            ]);

        return $events;
    }
}
