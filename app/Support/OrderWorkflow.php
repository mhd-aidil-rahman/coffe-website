<?php

namespace App\Support;

use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderWorkflow
{
    /** @param array{customer_name?: ?string, items?: list<array{menu_item_id?: int|string, quantity?: int|string}>} $data */
    public function create(StaffMember $waiter, array $data): object
    {
        $validated = Validator::make($data, [
            'customer_name' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.menu_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ])->validate();
        $menuIds = collect($validated['items'])->pluck('menu_item_id');
        $menus = DB::table('menu_items')
            ->whereIn('id', $menuIds)
            ->where('active', true)
            ->get()
            ->keyBy('id');

        if ($menus->count() !== $menuIds->count()) {
            throw ValidationException::withMessages(['items' => 'Satu atau lebih menu sudah tidak tersedia. Muat ulang daftar menu dan coba kembali.']);
        }

        $orderId = DB::transaction(function () use ($validated, $menus, $waiter): int {
            $totalAmount = collect($validated['items'])->sum(
                fn (array $item): int => $menus[$item['menu_item_id']]->price * $item['quantity'],
            );
            $orderId = DB::table('orders')->insertGetId([
                'staff_member_id' => $waiter->id,
                'waiter_name' => $waiter->name,
                'customer_name' => $validated['customer_name'] ?? null,
                'status' => 'sent_to_kitchen',
                'total_amount' => $totalAmount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $orderItems = collect($validated['items'])->map(function (array $item) use ($menus, $orderId): array {
                $menu = $menus[$item['menu_item_id']];
                $quantity = $item['quantity'];

                return [
                    'order_id' => $orderId,
                    'menu_item_id' => $menu->id,
                    'menu_name' => $menu->name,
                    'unit_price' => $menu->price,
                    'quantity' => $quantity,
                    'line_total' => $menu->price * $quantity,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            });

            DB::table('order_items')->insert($orderItems->all());

            return $orderId;
        });

        return $this->loadOrder($orderId);
    }

    /** @param array{payment_method?: string} $data */
    public function pay(StaffMember $waiter, int $orderId, array $data): object
    {
        $validated = Validator::make($data, [
            'payment_method' => ['required', Rule::in(['cash', 'qris', 'card'])],
        ])->validate();
        $order = $this->findVisibleOrder($waiter, $orderId);
        $updated = DB::table('orders')
            ->where('id', $orderId)
            ->where('staff_member_id', $waiter->id)
            ->where('status', 'sent_to_kitchen')
            ->update([
                'status' => 'completed',
                'payment_method' => $validated['payment_method'],
                'paid_at' => now(),
                'updated_at' => now(),
            ]);

        abort_unless($updated, 409, 'Pesanan sudah diproses dan tidak dapat dibayar lagi.');

        return $this->loadOrder($order->id);
    }

    /** @param array{reason?: ?string} $data */
    public function requestCancellation(StaffMember $waiter, int $orderId, array $data): object
    {
        $validated = Validator::make($data, [
            'reason' => ['nullable', 'string', 'max:255'],
        ])->validate();
        $this->findVisibleOrder($waiter, $orderId);
        $updated = DB::table('orders')
            ->where('id', $orderId)
            ->where('staff_member_id', $waiter->id)
            ->where('status', 'sent_to_kitchen')
            ->update([
                'status' => 'cancellation_pending',
                'cancellation_requested_at' => now(),
                'cancellation_reason' => $validated['reason'] ?? null,
                'void_code_hash' => null,
                'void_code_expires_at' => null,
                'updated_at' => now(),
            ]);

        abort_unless($updated, 409, 'Pesanan tidak dapat diajukan untuk pembatalan.');

        return $this->loadOrder($orderId);
    }

    /** @param array{void_code?: string} $data */
    public function void(StaffMember $waiter, int $orderId, array $data): object
    {
        $validated = Validator::make($data, [
            'void_code' => ['required', 'regex:/\A[0-9]{6}\z/'],
        ])->validate();
        $order = $this->findVisibleOrder($waiter, $orderId);

        abort_unless($order->status === 'cancellation_pending', 409, 'Pesanan tidak menunggu pembatalan.');
        if (! $order->void_code_hash || ! $order->void_code_expires_at) {
            throw ValidationException::withMessages(['void_code' => 'Permintaan masih menunggu persetujuan owner.'])->status(409);
        }
        if (now()->greaterThan($order->void_code_expires_at)) {
            throw ValidationException::withMessages(['void_code' => 'Kode Void sudah kedaluwarsa. Minta owner menerbitkan kode baru.'])->status(410);
        }

        if (! Hash::check($validated['void_code'], $order->void_code_hash)) {
            throw ValidationException::withMessages(['void_code' => 'Kode Void tidak valid.']);
        }

        $updated = DB::table('orders')
            ->where('id', $orderId)
            ->where('staff_member_id', $waiter->id)
            ->where('status', 'cancellation_pending')
            ->where('void_code_hash', $order->void_code_hash)
            ->where('void_code_expires_at', '>=', now())
            ->update([
                'status' => 'cancelled',
                'void_code_hash' => null,
                'void_code_expires_at' => null,
                'updated_at' => now(),
            ]);

        abort_unless($updated, 409, 'Kode Void sudah digunakan atau kedaluwarsa.');

        return $this->loadOrder($orderId);
    }

    /**
     * @param  array{decision?: string}  $data
     * @return array{order: object, void_code: ?string, void_code_expires_at: ?string}
     */
    public function decideCancellation(int $orderId, array $data): array
    {
        $validated = Validator::make($data, [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
        ])->validate();
        $order = DB::table('orders')
            ->where('id', $orderId)
            ->where('status', 'cancellation_pending')
            ->first();
        abort_unless($order, 404);

        $approved = $validated['decision'] === 'approve';
        $voidCode = $approved ? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT) : null;
        $expiresAt = $voidCode ? now()->addMinutes(15) : null;
        $updated = DB::table('orders')
            ->where('id', $orderId)
            ->where('status', 'cancellation_pending')
            ->update([
                'status' => $approved ? 'cancellation_pending' : 'sent_to_kitchen',
                'cancellation_requested_at' => $approved ? $order->cancellation_requested_at : null,
                'cancellation_reason' => $approved ? $order->cancellation_reason : null,
                'void_code_hash' => $voidCode ? Hash::make($voidCode) : null,
                'void_code_expires_at' => $expiresAt,
                'updated_at' => now(),
            ]);
        abort_unless($updated, 409, 'Permintaan pembatalan sudah diproses.');

        return ['order' => $this->loadOrder($orderId), 'void_code' => $voidCode, 'void_code_expires_at' => $expiresAt?->toIso8601String()];
    }

    public function query(User|StaffMember $account): Builder
    {
        $query = DB::table('orders')->orderByDesc('created_at')->orderByDesc('id');
        if ($account instanceof StaffMember && $account->position === 'Waiter') {
            $query->where('staff_member_id', $account->id);
        }

        return $query;
    }

    public function pendingCancellations(): Builder
    {
        return DB::table('orders')->where('status', 'cancellation_pending')->orderByDesc('cancellation_requested_at')->orderByDesc('id');
    }

    public function findVisibleOrder(User|StaffMember $account, int $orderId): object
    {
        $order = $this->query($account)->where('id', $orderId)->first();
        abort_unless($order, 404);

        return $this->withItems($order);
    }

    /** @param Collection<int, object> $orders
     * @return Collection<int, object> */
    public function withOrderItems(Collection $orders): Collection
    {
        $items = DB::table('order_items')->whereIn('order_id', $orders->pluck('id'))->orderBy('id')->get()->groupBy('order_id');

        return $orders->map(function (object $order) use ($items): object {
            $order->items = $items->get($order->id, collect());

            return $order;
        });
    }

    private function loadOrder(int $orderId): object
    {
        $order = DB::table('orders')->where('id', $orderId)->firstOrFail();

        return $this->withItems($order);
    }

    private function withItems(object $order): object
    {
        $order->items = DB::table('order_items')
            ->where('order_id', $order->id)
            ->orderBy('id')
            ->get();

        return $order;
    }
}
