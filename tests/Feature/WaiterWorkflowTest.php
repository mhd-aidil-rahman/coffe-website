<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WaiterWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_waiter_can_sign_in(): void
    {
        $waiterId = $this->createWaiter();

        $response = $this->post('/login', [
            'email' => 'waiter@example.test',
            'password' => 'waiter-pass-123',
        ]);

        $response->assertRedirect('/waiter/orders');
        $response->assertSessionHas('waiter_id', $waiterId);
    }

    public function test_waiter_order_is_visible_to_waiter_barista_and_owner(): void
    {
        $waiterId = $this->createWaiter();
        $baristaId = $this->createBarista();
        $menuId = $this->createMenuItem();

        $response = $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->post('/waiter/orders', [
                'customer_name' => 'Bagus',
                'items' => [$menuId => 2],
            ]);

        $response->assertRedirect('/waiter/orders');
        $this->assertDatabaseHas('orders', [
            'staff_member_id' => $waiterId,
            'customer_name' => 'Bagus',
            'status' => 'sent_to_kitchen',
            'total_amount' => 36000,
        ]);
        $this->assertDatabaseHas('order_items', [
            'menu_item_id' => $menuId,
            'menu_name' => 'Espresso',
            'unit_price' => 18000,
            'quantity' => 2,
            'line_total' => 36000,
        ]);

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->get('/waiter/orders')
            ->assertOk()
            ->assertSee('data-order-completion-notifications', false)
            ->assertSee('data-live-sync="waiter-menu-list"', false)
            ->assertSee('data-live-sync="waiter-active-orders"', false)
            ->assertSee('Order #1')
            ->assertSee('Bagus');

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/barista/operational')
            ->assertOk()
            ->assertSee('data-live-sync="barista-incoming-orders"', false)
            ->assertSee('Order #1')
            ->assertSee('Bagus');

        $this->withSession(['owner_logged_in' => true])
            ->get('/owner/dashboard')
            ->assertOk()
            ->assertSee('data-order-completion-notifications', false)
            ->assertSee('data-live-sync="owner-metrics"', false)
            ->assertSee('data-live-sync="owner-recent-orders"', false)
            ->assertSee('Order #1')
            ->assertSee('Bagus')
            ->assertSee('Rp 36.000');
    }

    public function test_payment_is_visible_in_owner_financial_report(): void
    {
        $this->freezeTime();
        $waiterId = $this->createWaiter();
        $baristaId = $this->createBarista();
        $menuId = $this->createMenuItem();
        $orderId = $this->createOrder($waiterId, $menuId);
        $afterPaidAt = now()->toIso8601String();
        $notificationCursor = ['after_paid_at' => $afterPaidAt, 'after_order_id' => 0];

        $this->withSession(['owner_logged_in' => true])
            ->get('/notifications/completed-orders?'.http_build_query($notificationCursor))
            ->assertOk()
            ->assertJsonCount(0, 'events');

        $response = $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->post('/waiter/orders/'.$orderId.'/pay', ['payment_method' => 'qris']);

        $response->assertRedirect('/waiter/orders');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'completed',
            'payment_method' => 'qris',
        ]);

        $this->withSession(['owner_logged_in' => true])
            ->get('/notifications/completed-orders?'.http_build_query($notificationCursor))
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.id', $orderId)
            ->assertJsonPath('events.0.payment_method', 'qris');

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->get('/notifications/completed-orders?'.http_build_query($notificationCursor))
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.id', $orderId);

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/notifications/completed-orders?'.http_build_query($notificationCursor))
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.id', $orderId);

        $this->withSession(['owner_logged_in' => true])
            ->get('/owner/laporan')
            ->assertOk()
            ->assertSee('data-live-sync="owner-financial-summary"', false)
            ->assertSee('data-live-sync="owner-recent-transactions"', false)
            ->assertSee('Rp 18.000')
            ->assertSee('QRIS')
            ->assertSee('Order #'.$orderId);

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/barista/operational?period=today')
            ->assertOk()
            ->assertSee('data-order-completion-notifications', false)
            ->assertSee('data-live-sync="barista-financial-summary"', false)
            ->assertSee('Ringkasan Keuangan')
            ->assertSee('Rp 18.000')
            ->assertSee('Order #'.$orderId);
    }

    public function test_completed_order_notifications_require_a_valid_staff_account(): void
    {
        $waiterId = $this->createWaiter();
        $afterPaidAt = now()->toIso8601String();
        $url = '/notifications/completed-orders?'.http_build_query([
            'after_paid_at' => $afterPaidAt,
            'after_order_id' => 0,
        ]);

        $this->get($url)->assertUnauthorized();

        DB::table('staff_members')->where('id', $waiterId)->update(['active' => false]);

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->get($url)
            ->assertUnauthorized();
    }

    public function test_owner_can_approve_waiter_cancellation_request(): void
    {
        $this->freezeTime();
        $notificationCursor = [
            'after_paid_at' => now()->toIso8601String(),
            'after_order_id' => 0,
        ];
        $waiterId = $this->createWaiter();
        $menuId = $this->createMenuItem();
        $orderId = $this->createOrder($waiterId, $menuId);

        $requestResponse = $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->post('/waiter/orders/'.$orderId.'/cancel', ['reason' => 'Pelanggan salah pesan']);

        $requestResponse->assertRedirect('/waiter/orders');
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'cancellation_pending',
            'cancellation_reason' => 'Pelanggan salah pesan',
        ]);

        $ownerResponse = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/orders/'.$orderId.'/cancellation', ['decision' => 'approve']);

        $ownerResponse->assertRedirect('/owner/operational');
        $ownerResponse->assertSessionHas('void_code');
        $voidCode = $ownerResponse->getSession()->get('void_code');
        $this->withSession(['owner_logged_in' => true])
            ->get('/notifications/completed-orders?'.http_build_query($notificationCursor))
            ->assertOk()
            ->assertJsonCount(0, 'events');
        $this->assertMatchesRegularExpression('/\A[0-9]{6}\z/', $voidCode);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'cancellation_pending',
            'void_code_expires_at' => now()->addMinutes(15)->toDateTimeString(),
        ]);
        $order = DB::table('orders')->where('id', $orderId)->first();
        $this->assertNotSame($voidCode, $order->void_code_hash);
        $this->assertTrue(Hash::check($voidCode, $order->void_code_hash));

        $this->withSession(['owner_logged_in' => true, 'void_code' => $voidCode, 'void_code_order_id' => $orderId])
            ->get('/owner/operational')
            ->assertOk()
            ->assertSee($voidCode)
            ->assertSee('hanya dapat digunakan sekali');

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->get('/waiter/orders')
            ->assertOk()
            ->assertSee('Disetujui owner')
            ->assertSee('name="void_code"', false)
            ->assertDontSee($voidCode)
            ->assertDontSee($order->void_code_hash);

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->post('/waiter/orders/'.$orderId.'/void', ['void_code' => $voidCode])
            ->assertRedirect('/waiter/orders')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'cancelled',
            'void_code_hash' => null,
            'void_code_expires_at' => null,
        ]);

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->post('/waiter/orders/'.$orderId.'/void', ['void_code' => $voidCode])
            ->assertNotFound();
    }

    public function test_waiter_cannot_cancel_without_an_owner_issued_void_code(): void
    {
        $waiterId = $this->createWaiter();
        $menuId = $this->createMenuItem();
        $orderId = $this->createOrder($waiterId, $menuId);

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->post('/waiter/orders/'.$orderId.'/cancel', ['reason' => 'Pelanggan berubah pikiran'])
            ->assertRedirect('/waiter/orders');

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->post('/waiter/orders/'.$orderId.'/void', ['void_code' => '123456'])
            ->assertRedirect('/waiter/orders')
            ->assertSessionHasErrors('void_code');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'cancellation_pending',
            'void_code_hash' => null,
        ]);
    }

    public function test_wrong_or_expired_void_code_does_not_cancel_order(): void
    {
        $waiterId = $this->createWaiter();
        $menuId = $this->createMenuItem();
        $orderId = $this->createOrder($waiterId, $menuId);

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->post('/waiter/orders/'.$orderId.'/cancel', ['reason' => 'Pelanggan salah pesan']);

        $ownerResponse = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/orders/'.$orderId.'/cancellation', ['decision' => 'approve']);
        $voidCode = $ownerResponse->getSession()->get('void_code');

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->post('/waiter/orders/'.$orderId.'/void', ['void_code' => '000000'])
            ->assertRedirect('/waiter/orders')
            ->assertSessionHasErrors('void_code');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'cancellation_pending']);

        $this->travel(16)->minutes();

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->post('/waiter/orders/'.$orderId.'/void', ['void_code' => $voidCode])
            ->assertRedirect('/waiter/orders')
            ->assertSessionHasErrors('void_code');

        $this->assertDatabaseHas('orders', ['id' => $orderId, 'status' => 'cancellation_pending']);
    }

    public function test_void_code_cannot_cancel_another_waiters_order(): void
    {
        $orderWaiterId = $this->createWaiter();
        $otherWaiterId = DB::table('staff_members')->insertGetId([
            'name' => 'Other Waiter',
            'email' => 'other-waiter@example.test',
            'position' => 'Waiter',
            'password' => Hash::make('waiter-pass-123'),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $menuId = $this->createMenuItem();
        $orderId = $this->createOrder($orderWaiterId, $menuId);

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $orderWaiterId])
            ->post('/waiter/orders/'.$orderId.'/cancel', ['reason' => 'Pelanggan salah pesan']);

        $ownerResponse = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/orders/'.$orderId.'/cancellation', ['decision' => 'approve']);
        $voidCode = $ownerResponse->getSession()->get('void_code');

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $otherWaiterId])
            ->post('/waiter/orders/'.$orderId.'/void', ['void_code' => $voidCode])
            ->assertNotFound();

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'staff_member_id' => $orderWaiterId,
            'status' => 'cancellation_pending',
        ]);
    }

    public function test_owner_rejection_clears_pending_void_code_approval(): void
    {
        $waiterId = $this->createWaiter();
        $menuId = $this->createMenuItem();
        $orderId = $this->createOrder($waiterId, $menuId);

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->post('/waiter/orders/'.$orderId.'/cancel', ['reason' => 'Pelanggan salah pesan']);

        $this->withSession(['owner_logged_in' => true])
            ->post('/owner/orders/'.$orderId.'/cancellation', ['decision' => 'approve']);

        $this->withSession(['owner_logged_in' => true])
            ->post('/owner/orders/'.$orderId.'/cancellation', ['decision' => 'reject'])
            ->assertRedirect('/owner/operational');

        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'sent_to_kitchen',
            'void_code_hash' => null,
            'void_code_expires_at' => null,
            'cancellation_requested_at' => null,
            'cancellation_reason' => null,
        ]);
    }

    public function test_waiter_cannot_order_inactive_menu(): void
    {
        $waiterId = $this->createWaiter();
        $menuId = $this->createMenuItem(active: false);

        $response = $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->post('/waiter/orders', ['items' => [$menuId => 1]]);

        $response->assertRedirect('/waiter/orders');
        $response->assertSessionHasErrors('items');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_waiter_cannot_access_owner_dashboard(): void
    {
        $waiterId = $this->createWaiter();

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->get('/owner/dashboard')
            ->assertRedirect('/login');
    }

    private function createWaiter(): int
    {
        return DB::table('staff_members')->insertGetId([
            'name' => 'Waiter Gotham',
            'email' => 'waiter@example.test',
            'position' => 'Waiter',
            'password' => Hash::make('waiter-pass-123'),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createBarista(): int
    {
        return DB::table('staff_members')->insertGetId([
            'name' => 'Barista Gotham',
            'email' => 'barista@example.test',
            'position' => 'Barista',
            'password' => Hash::make('barista-pass-123'),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMenuItem(bool $active = true): int
    {
        $categoryId = DB::table('menu_categories')->insertGetId([
            'name' => 'Coffee',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'Espresso',
            'price' => 18000,
            'active' => $active,
            'description' => 'Coffee',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(int $waiterId, int $menuId): int
    {
        $orderId = DB::table('orders')->insertGetId([
            'staff_member_id' => $waiterId,
            'waiter_name' => 'Waiter Gotham',
            'customer_name' => null,
            'status' => 'sent_to_kitchen',
            'total_amount' => 18000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'menu_item_id' => $menuId,
            'menu_name' => 'Espresso',
            'unit_price' => 18000,
            'quantity' => 1,
            'line_total' => 18000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $orderId;
    }
}
