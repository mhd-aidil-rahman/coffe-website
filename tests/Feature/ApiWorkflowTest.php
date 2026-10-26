<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_waiter_can_log_in_and_create_an_order_using_server_menu_prices(): void
    {
        $waiterId = $this->createStaff('Waiter', 'waiter@example.test');
        $menuId = $this->createMenuItem('Latte', 22000);
        $token = $this->login('waiter@example.test', 'password123');

        $response = $this->withToken($token)->postJson('/api/v1/orders', [
            'customer_name' => 'Rani',
            'items' => [
                ['menu_item_id' => $menuId, 'quantity' => 2, 'unit_price' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'sent_to_kitchen')
            ->assertJsonPath('data.total_amount', 44000)
            ->assertJsonPath('data.items.0.unit_price', 22000)
            ->assertJsonPath('data.items.0.line_total', 44000);
        $this->assertDatabaseHas('orders', [
            'staff_member_id' => $waiterId,
            'customer_name' => 'Rani',
            'status' => 'sent_to_kitchen',
            'total_amount' => 44000,
        ]);
        $this->assertDatabaseHas('order_items', [
            'menu_item_id' => $menuId,
            'unit_price' => 22000,
            'quantity' => 2,
            'line_total' => 44000,
        ]);
    }

    public function test_barista_can_change_menu_availability_but_waiter_cannot(): void
    {
        $this->createStaff('Waiter', 'waiter@example.test');
        $this->createStaff('Barista', 'barista@example.test');
        $menuId = $this->createMenuItem('Espresso', 18000);
        $waiterToken = $this->login('waiter@example.test', 'password123');
        $baristaToken = $this->login('barista@example.test', 'password123');

        $this->withToken($waiterToken)
            ->patchJson('/api/v1/menu-items/'.$menuId.'/availability', ['active' => false])
            ->assertForbidden();

        $this->forgetAuthentication();
        $this->withToken($baristaToken)
            ->patchJson('/api/v1/menu-items/'.$menuId.'/availability', ['active' => false])
            ->assertOk()
            ->assertJsonPath('data.active', false);
        $this->assertDatabaseHas('menu_items', ['id' => $menuId, 'active' => false]);

        $this->forgetAuthentication();
        $this->withToken($waiterToken)
            ->getJson('/api/v1/menu-items')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_waiter_cannot_read_another_waiters_order(): void
    {
        $waiterId = $this->createStaff('Waiter', 'waiter@example.test');
        $otherWaiterId = $this->createStaff('Waiter', 'other@example.test');
        $menuId = $this->createMenuItem('Americano', 16000);
        $orderId = $this->createOrder($otherWaiterId, $menuId);
        $token = $this->login('waiter@example.test', 'password123');

        $this->withToken($token)
            ->getJson('/api/v1/orders/'.$orderId)
            ->assertNotFound();
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'staff_member_id' => $otherWaiterId]);
    }

    public function test_owner_approves_void_and_waiter_can_cancel_once_with_the_code(): void
    {
        $this->freezeTime();
        $waiterId = $this->createStaff('Waiter', 'waiter@example.test');
        $menuId = $this->createMenuItem('Cappuccino', 24000);
        $orderId = $this->createOrder($waiterId, $menuId);
        $waiterToken = $this->login('waiter@example.test', 'password123');
        $ownerToken = $this->login('owner@gothamcoffee.id', 'password123');

        $this->withToken($waiterToken)
            ->postJson('/api/v1/orders/'.$orderId.'/cancellation-requests', [
                'reason' => 'Pelanggan salah pesan',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.status', 'cancellation_pending');

        $this->forgetAuthentication();
        $approval = $this->withToken($ownerToken)
            ->patchJson('/api/v1/owner/orders/'.$orderId.'/cancellation', ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.order.status', 'cancellation_pending');
        $this->assertStringNotContainsString('void_code_hash', $approval->getContent());
        $voidCode = $approval->json('data.void_code');
        $this->assertMatchesRegularExpression('/\A[0-9]{6}\z/', $voidCode);
        $savedHash = DB::table('orders')->where('id', $orderId)->value('void_code_hash');
        $this->assertNotSame($voidCode, $savedHash);
        $this->assertTrue(Hash::check($voidCode, $savedHash));

        $this->forgetAuthentication();
        $voidResponse = $this->withToken($waiterToken)
            ->postJson('/api/v1/orders/'.$orderId.'/void', ['void_code' => $voidCode]);
        $voidResponse->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertStringNotContainsString($voidCode, $voidResponse->getContent());
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'status' => 'cancelled',
            'void_code_hash' => null,
        ]);

        $this->forgetAuthentication();
        $this->withToken($waiterToken)
            ->postJson('/api/v1/orders/'.$orderId.'/void', ['void_code' => $voidCode])
            ->assertConflict();
    }

    public function test_barista_transactions_flow_into_owner_financial_report(): void
    {
        $this->createStaff('Waiter', 'waiter@example.test');
        $this->createStaff('Barista', 'barista@example.test');
        $menuId = $this->createMenuItem('Mocha', 24000);
        $orderId = $this->createOrder(
            (int) DB::table('staff_members')->where('email', 'waiter@example.test')->value('id'),
            $menuId,
        );
        DB::table('orders')->where('id', $orderId)->update([
            'status' => 'completed',
            'payment_method' => 'qris',
            'paid_at' => now(),
        ]);
        $token = $this->login('barista@example.test', 'password123');

        $this->withToken($token)
            ->postJson('/api/v1/barista/transactions', [
                'type' => 'expense',
                'category' => 'Bahan Baku',
                'description' => 'Pembelian susu',
                'amount' => 35000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', 35000);

        $this->withToken($token)
            ->getJson('/api/v1/barista/transactions?period=today')
            ->assertOk()
            ->assertJsonCount(2, 'data.transactions')
            ->assertJsonFragment([
                'source' => 'order',
                'description' => 'Order #'.$orderId.' · Test Customer',
                'amount' => 24000,
            ]);

        $ownerToken = $this->login('owner@gothamcoffee.id', 'password123');
        $this->forgetAuthentication();
        $this->withToken($ownerToken)
            ->getJson('/api/v1/owner/reports/financial')
            ->assertOk()
            ->assertJsonPath('data.summary.expenses', 35000)
            ->assertJsonPath('data.summary.income', 24000)
            ->assertJsonPath('data.summary.net', -11000)
            ->assertJsonPath('data.expenses_by_category.0.category', 'Bahan Baku');
    }

    public function test_inactive_staff_cannot_use_a_previously_issued_token(): void
    {
        $staffId = $this->createStaff('Waiter', 'waiter@example.test');
        $token = $this->login('waiter@example.test', 'password123');
        DB::table('staff_members')->where('id', $staffId)->update(['active' => false]);

        $this->forgetAuthentication();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_owner_can_create_staff_without_exposing_the_password(): void
    {
        $ownerToken = $this->login('owner@gothamcoffee.id', 'password123');

        $response = $this->withToken($ownerToken)->postJson('/api/v1/owner/staff', [
            'name' => 'Dina Waiter',
            'email' => 'dina@example.test',
            'position' => 'Waiter',
            'password' => 'dina-pass-123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'dina@example.test')
            ->assertJsonPath('data.position', 'Waiter');
        $this->assertArrayNotHasKey('password', $response->json('data'));
        $this->assertTrue(Hash::check(
            'dina-pass-123',
            DB::table('staff_members')->where('email', 'dina@example.test')->value('password'),
        ));
    }

    public function test_logout_revokes_the_current_api_token(): void
    {
        $this->createStaff('Waiter', 'waiter@example.test');
        $token = $this->login('waiter@example.test', 'password123');

        $this->withToken($token)
            ->deleteJson('/api/v1/auth/logout')
            ->assertOk();

        $this->forgetAuthentication();
        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_web_owner_login_uses_database_credentials(): void
    {
        User::factory()->create(['email' => 'owner@example.test', 'password' => 'a-strong-owner-password']);

        $this->post('/login', [
            'email' => 'owner@example.test',
            'password' => 'a-strong-owner-password',
        ])->assertRedirect('/owner/dashboard')
            ->assertSessionHas('owner_logged_in', true);
    }

    public function test_completed_order_notifications_are_available_to_all_three_roles_only_after_payment(): void
    {
        $this->freezeTime();
        $waiterId = $this->createStaff('Waiter', 'waiter@example.test');
        $baristaId = $this->createStaff('Barista', 'barista@example.test');
        $menuId = $this->createMenuItem('Flat White', 26000);
        $paidOrderId = $this->createOrder($waiterId, $menuId);
        DB::table('orders')->where('id', $paidOrderId)->update([
            'status' => 'completed',
            'payment_method' => 'qris',
            'paid_at' => now(),
        ]);
        $cancelledOrderId = $this->createOrder($waiterId, $menuId);
        DB::table('orders')->where('id', $cancelledOrderId)->update([
            'status' => 'cancelled',
            'paid_at' => now(),
        ]);
        $cursor = [
            'after_paid_at' => now()->subSecond()->toIso8601String(),
            'after_order_id' => 0,
        ];
        $url = '/api/v1/notifications/completed-orders?'.http_build_query($cursor);

        $waiterToken = $this->login('waiter@example.test', 'password123');
        $this->withToken($waiterToken)
            ->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.id', $paidOrderId);

        $baristaToken = $this->login('barista@example.test', 'password123');
        $this->forgetAuthentication();
        $this->withToken($baristaToken)
            ->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.id', $paidOrderId);

        $ownerToken = $this->login('owner@gothamcoffee.id', 'password123');
        $this->forgetAuthentication();
        $this->withToken($ownerToken)
            ->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.id', $paidOrderId);
    }

    private function forgetAuthentication(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function login(string $email, string $password): string
    {
        if ($email === 'owner@gothamcoffee.id') {
            User::factory()->create(['email' => $email, 'password' => $password]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => 'phpunit',
        ]);

        $response->assertCreated();

        return $response->json('data.token');
    }

    private function createStaff(string $position, string $email): int
    {
        return DB::table('staff_members')->insertGetId([
            'name' => ucfirst($position).' Test',
            'email' => $email,
            'position' => $position,
            'password' => Hash::make('password123'),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMenuItem(string $name, int $price): int
    {
        $categoryId = DB::table('menu_categories')->insertGetId([
            'name' => 'Coffee',
            'description' => 'Minuman kopi',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId,
            'name' => $name,
            'price' => $price,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(int $waiterId, int $menuId): int
    {
        $menu = DB::table('menu_items')->where('id', $menuId)->first();
        $orderId = DB::table('orders')->insertGetId([
            'staff_member_id' => $waiterId,
            'waiter_name' => DB::table('staff_members')->where('id', $waiterId)->value('name'),
            'customer_name' => 'Test Customer',
            'status' => 'sent_to_kitchen',
            'total_amount' => $menu->price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'menu_item_id' => $menuId,
            'menu_name' => $menu->name,
            'unit_price' => $menu->price,
            'quantity' => 1,
            'line_total' => $menu->price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $orderId;
    }
}
