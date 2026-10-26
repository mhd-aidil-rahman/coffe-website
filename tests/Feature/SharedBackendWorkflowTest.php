<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SharedBackendWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_created_on_web_can_sign_in_through_api(): void
    {
        $this->withSession(['owner_logged_in' => true])->post('/owner/staff', [
            'name' => 'Dina', 'email' => 'dina@example.test', 'position' => 'Waiter', 'password' => 'password123',
        ])->assertRedirect('/owner/staff');
        $this->assertDatabaseHas('staff_members', ['email' => 'dina@example.test', 'position' => 'Waiter', 'active' => true]);
        $this->postJson('/api/v1/auth/login', ['email' => 'dina@example.test', 'password' => 'password123'])
            ->assertCreated()->assertJsonPath('data.user.name', 'Dina')->assertJsonPath('data.user.role', 'Waiter');
    }

    public function test_api_menu_changes_are_visible_on_web_and_web_availability_blocks_mobile_orders_with_422(): void
    {
        $category = $this->category();
        $waiter = $this->staff('Waiter');
        $ownerToken = $this->token('owner@gothamcoffee.id');
        $menu = $this->withToken($ownerToken)->postJson('/api/v1/menu-items', [
            'name' => 'Shared Latte', 'price' => 22000, 'category_id' => $category,
        ])->assertCreated()->json('data.id');
        $this->withSession(['owner_logged_in' => true])->get('/owner/menu')->assertOk()->assertSee('Shared Latte');
        $this->post('/owner/menu/'.$menu.'/toggle')->assertRedirect('/owner/menu?tab=menu');
        $this->assertDatabaseHas('menu_items', ['id' => $menu, 'active' => false]);
        $this->resetApiGuard();
        $waiterToken = $this->token('waiter@example.test');
        $this->withToken($waiterToken)->postJson('/api/v1/orders', ['items' => [['menu_item_id' => $menu, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_web_order_uses_server_price_and_api_payment_updates_web_report(): void
    {
        $this->freezeTime();
        $waiter = $this->staff('Waiter');
        $menu = $this->menu();
        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiter])->post('/waiter/orders', [
            'customer_name' => 'Bruce', 'items' => [$menu => 2], 'total_amount' => 1,
        ])->assertRedirect('/waiter/orders');
        $order = (int) DB::table('orders')->value('id');
        $this->assertDatabaseHas('orders', ['id' => $order, 'total_amount' => 44000, 'status' => 'sent_to_kitchen']);
        $this->assertDatabaseHas('order_items', ['order_id' => $order, 'quantity' => 2, 'unit_price' => 22000]);
        $token = $this->token('waiter@example.test');
        $this->withToken($token)->getJson('/api/v1/orders/'.$order)->assertOk()->assertJsonPath('data.total_amount', 44000);
        $this->withToken($token)->postJson('/api/v1/orders/'.$order.'/payments', ['payment_method' => 'qris'])
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseHas('orders', ['id' => $order, 'status' => 'completed', 'payment_method' => 'qris']);
        $this->withSession(['owner_logged_in' => true])->get('/owner/laporan?period=today')
            ->assertOk()->assertViewHas('income', 44000)->assertViewHas('profit', 44000);
    }

    public function test_api_cancellation_can_be_approved_on_web_and_voided_once_through_api(): void
    {
        $this->freezeTime();
        $this->staff('Waiter');
        $menu = $this->menu();
        $token = $this->token('waiter@example.test');
        $order = $this->withToken($token)->postJson('/api/v1/orders', ['items' => [['menu_item_id' => $menu, 'quantity' => 1]]])
            ->assertCreated()->json('data.id');
        $this->withToken($token)->postJson('/api/v1/orders/'.$order.'/cancellation-requests', ['reason' => 'Salah pesan'])
            ->assertAccepted();
        $approval = $this->withSession(['owner_logged_in' => true])->post('/owner/orders/'.$order.'/cancellation', ['decision' => 'approve'])
            ->assertRedirect('/owner/operational')->assertSessionHas('void_code');
        $code = session('void_code');
        $this->assertTrue(Hash::check($code, DB::table('orders')->where('id', $order)->value('void_code_hash')));
        $this->resetApiGuard();
        $this->withToken($token)->postJson('/api/v1/orders/'.$order.'/void', ['void_code' => $code])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertDatabaseHas('orders', ['id' => $order, 'status' => 'cancelled', 'void_code_hash' => null]);
        $this->withToken($token)->postJson('/api/v1/orders/'.$order.'/void', ['void_code' => $code])->assertStatus(409);
    }

    public function test_password_changed_on_web_revokes_mobile_token_and_both_logins_use_new_password(): void
    {
        $waiter = $this->staff('Waiter');
        $oldToken = $this->token('waiter@example.test');
        $this->withSession(['owner_logged_in' => true])->post('/owner/staff/'.$waiter.'/update', [
            'name' => 'Waiter', 'email' => 'waiter@example.test', 'position' => 'Waiter', 'password' => 'new-password-123',
        ])->assertRedirect('/owner/staff');
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->resetApiGuard();
        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->post('/login', ['email' => 'waiter@example.test', 'password' => 'new-password-123'])
            ->assertRedirect('/waiter/orders')->assertSessionHas('waiter_id', $waiter);
        $this->postJson('/api/v1/auth/login', ['email' => 'waiter@example.test', 'password' => 'new-password-123'])
            ->assertCreated()->assertJsonPath('data.user.id', $waiter);
    }

    public function test_staff_disabled_on_web_loses_web_and_mobile_access(): void
    {
        $waiter = $this->staff('Waiter');
        $token = $this->token('waiter@example.test');
        $this->withSession(['owner_logged_in' => true])->post('/owner/staff/'.$waiter.'/toggle')->assertRedirect('/owner/staff');
        $this->assertDatabaseHas('staff_members', ['id' => $waiter, 'active' => false]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->resetApiGuard();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->withSession(['owner_logged_in' => false, 'waiter_logged_in' => true, 'waiter_id' => $waiter])
            ->get('/waiter/orders')->assertRedirect('/login');
        $this->postJson('/api/v1/auth/login', ['email' => 'waiter@example.test', 'password' => 'password123'])->assertUnauthorized();
    }

    public function test_bar_transaction_written_on_web_has_identical_financial_totals_in_api_and_web(): void
    {
        $this->travelTo('2026-10-13 12:00:00');
        $barista = $this->staff('Barista');
        $this->withSession(['barista_logged_in' => true, 'barista_id' => $barista])->post('/barista/transactions', [
            'type' => 'income', 'category' => 'Penjualan', 'description' => 'Penjualan bar', 'amount' => 50000,
        ])->assertRedirect('/barista/operational');
        $this->post('/barista/transactions', [
            'type' => 'expense', 'category' => 'Bahan Baku', 'description' => 'Pembelian susu', 'amount' => 12000,
        ])->assertRedirect('/barista/operational');
        $this->assertDatabaseHas('bar_transactions', ['staff_member_id' => $barista, 'amount' => 12000, 'type' => 'expense']);
        $ownerToken = $this->token('owner@gothamcoffee.id');
        $this->withToken($ownerToken)->getJson('/api/v1/owner/reports/financial?start_date=2026-10-13&end_date=2026-10-13')
            ->assertOk()->assertJsonPath('data.summary.income', 50000)->assertJsonPath('data.summary.expenses', 12000)
            ->assertJsonPath('data.summary.net', 38000)->assertJsonPath('data.daily_trends.0.net_amount', 38000);
        $this->withSession(['owner_logged_in' => true])->get('/owner/laporan?period=today')
            ->assertOk()->assertViewHas('income', 50000)->assertViewHas('expenses', 12000)->assertViewHas('profit', 38000);
    }

    public function test_switching_web_login_from_barista_to_waiter_clears_previous_role(): void
    {
        $barista = $this->staff('Barista');
        $waiter = $this->staff('Waiter');
        $this->withSession(['barista_logged_in' => true, 'barista_id' => $barista, 'owner_logged_in' => false])->post('/login', [
            'email' => 'waiter@example.test', 'password' => 'password123',
        ])->assertRedirect('/waiter/orders')->assertSessionHas('waiter_id', $waiter)
            ->assertSessionMissing('barista_logged_in')->assertSessionMissing('barista_id');
    }

    public function test_category_with_menu_cannot_be_deleted_from_either_web_or_api(): void
    {
        $menu = $this->menu();
        $category = (int) DB::table('menu_items')->where('id', $menu)->value('category_id');
        $ownerToken = $this->token('owner@gothamcoffee.id');
        $this->withToken($ownerToken)->deleteJson('/api/v1/menu-categories/'.$category)
            ->assertStatus(409)->assertJsonValidationErrors('category');
        $this->withSession(['owner_logged_in' => true])->post('/owner/categories/'.$category.'/delete')
            ->assertRedirect('/owner/menu?tab=categories')->assertSessionHasErrors('category');
        $this->assertDatabaseHas('menu_categories', ['id' => $category]);
        $this->assertDatabaseHas('menu_items', ['id' => $menu]);
    }

    public function test_reports_do_not_expose_void_code_hashes(): void
    {
        $this->freezeTime();
        $waiter = $this->staff('Waiter');
        DB::table('orders')->insert([
            'staff_member_id' => $waiter, 'waiter_name' => 'Waiter', 'status' => 'cancellation_pending',
            'total_amount' => 22000, 'cancellation_requested_at' => now(),
            'void_code_hash' => Hash::make('123456'), 'void_code_expires_at' => now()->addMinutes(15),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $ownerToken = $this->token('owner@gothamcoffee.id');
        $this->withToken($ownerToken)->getJson('/api/v1/owner/dashboard')
            ->assertOk()->assertDontSee('void_code_hash')->assertDontSee('void_code_expires_at');
        $this->getJson('/api/v1/owner/reports/financial')
            ->assertOk()->assertJsonCount(1, 'data.cancellations')->assertDontSee('void_code_hash');
    }

    public function test_role_changed_on_api_revokes_old_token_and_previous_web_role(): void
    {
        $waiter = $this->staff('Waiter');
        $oldToken = $this->token('waiter@example.test');
        $ownerToken = $this->token('owner@gothamcoffee.id');
        $this->withToken($ownerToken)->patchJson('/api/v1/owner/staff/'.$waiter, [
            'name' => 'New Barista', 'email' => 'waiter@example.test', 'position' => 'Barista',
        ])->assertOk()->assertJsonPath('data.position', 'Barista');
        $this->assertDatabaseHas('staff_members', ['id' => $waiter, 'position' => 'Barista']);
        $this->resetApiGuard();
        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiter])
            ->get('/waiter/orders')->assertRedirect('/login');
    }

    public function test_staff_deleted_on_api_revokes_token_and_blocks_web_login(): void
    {
        $waiter = $this->staff('Waiter');
        $oldToken = $this->token('waiter@example.test');
        $ownerToken = $this->token('owner@gothamcoffee.id');
        $this->withToken($ownerToken)->deleteJson('/api/v1/owner/staff/'.$waiter)->assertOk();
        $this->assertDatabaseMissing('staff_members', ['id' => $waiter]);
        $this->resetApiGuard();
        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->post('/login', ['email' => 'waiter@example.test', 'password' => 'password123'])
            ->assertSessionHasErrors('email');
    }

    private function resetApiGuard(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function token(string $email): string
    {
        if ($email === 'owner@gothamcoffee.id') {
            User::factory()->create(['email' => $email, 'password' => 'password123']);
        }
        $this->resetApiGuard();
        $response = $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password123'])->assertCreated();
        $this->resetApiGuard();

        return $response->json('data.token');
    }

    private function staff(string $role): int
    {
        return DB::table('staff_members')->insertGetId([
            'name' => $role, 'email' => strtolower($role).'@example.test', 'position' => $role,
            'password' => Hash::make('password123'), 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function category(): int
    {
        return DB::table('menu_categories')->insertGetId(['name' => 'Coffee', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function menu(): int
    {
        return DB::table('menu_items')->insertGetId([
            'category_id' => $this->category(), 'name' => 'Shared Latte', 'price' => 22000, 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
