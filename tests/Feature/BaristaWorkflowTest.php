<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BaristaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_barista_can_sign_in_to_operational_page(): void
    {
        $baristaId = $this->createBarista();

        $response = $this->post('/login', [
            'email' => 'barista@example.test',
            'password' => 'barista-pass-123',
        ]);

        $response->assertRedirect('/barista/operational');
        $response->assertSessionHas('barista_id', $baristaId);
    }

    public function test_barista_can_record_a_transaction_visible_to_owner(): void
    {
        $baristaId = $this->createBarista();

        $response = $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->post('/barista/transactions', [
                'type' => 'expense',
                'category' => 'Bahan Baku',
                'description' => 'Pembelian susu',
                'amount' => '75000',
                'notes' => 'Untuk stok harian',
            ]);

        $response->assertRedirect('/barista/operational');
        $this->assertDatabaseHas('bar_transactions', [
            'staff_member_id' => $baristaId,
            'type' => 'expense',
            'category' => 'Bahan Baku',
            'description' => 'Pembelian susu',
            'amount' => 75000,
        ]);

        $this->withSession(['owner_logged_in' => true])
            ->get('/owner/laporan')
            ->assertOk()
            ->assertSee('Rp 75.000')
            ->assertSee('Pembelian susu');
    }

    public function test_barista_summary_shares_transactions_and_paid_orders_across_staff_for_selected_period(): void
    {
        $baristaId = $this->createBarista();
        $otherBaristaId = DB::table('staff_members')->insertGetId([
            'name' => 'Second Barista',
            'email' => 'second-barista@example.test',
            'position' => 'Barista',
            'password' => Hash::make('second-pass-123'),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('bar_transactions')->insert([
            'staff_member_id' => $otherBaristaId,
            'type' => 'expense',
            'category' => 'Bahan Baku',
            'description' => 'Pembelian susu bersama',
            'amount' => 12500,
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('bar_transactions')->insert([
            'staff_member_id' => $otherBaristaId,
            'type' => 'expense',
            'category' => 'Peralatan',
            'description' => 'Pengeluaran lama',
            'amount' => 99000,
            'occurred_at' => now()->subDays(8),
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);

        $menuId = $this->createMenuItem();
        $orderId = $this->createOrder($baristaId, $menuId);
        DB::table('orders')->where('id', $orderId)->update([
            'status' => 'completed',
            'payment_method' => 'cash',
            'paid_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/barista/operational?period=7days')
            ->assertOk()
            ->assertSee('Pembelian susu bersama')
            ->assertSee('Rp 18.000')
            ->assertSee('Rp 12.500')
            ->assertSee('Rp 5.500')
            ->assertDontSee('Pengeluaran lama');
    }

    public function test_owner_financial_report_period_includes_shared_bar_income_and_expenses(): void
    {
        $baristaId = $this->createBarista();
        DB::table('bar_transactions')->insert([
            [
                'staff_member_id' => $baristaId,
                'type' => 'income',
                'category' => 'Penjualan',
                'description' => 'Penjualan bar hari ini',
                'amount' => 10000,
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'staff_member_id' => $baristaId,
                'type' => 'expense',
                'category' => 'Operasional',
                'description' => 'Listrik bar hari ini',
                'amount' => 3000,
                'occurred_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'staff_member_id' => $baristaId,
                'type' => 'expense',
                'category' => 'Operasional',
                'description' => 'Pengeluaran periode lama',
                'amount' => 90000,
                'occurred_at' => now()->subDays(35),
                'created_at' => now()->subDays(35),
                'updated_at' => now()->subDays(35),
            ],
        ]);

        $this->withSession(['owner_logged_in' => true])
            ->get('/owner/laporan?period=today')
            ->assertOk()
            ->assertSee('Rp 10.000')
            ->assertSee('Rp 3.000')
            ->assertSee('Rp 7.000')
            ->assertSee('Penjualan bar hari ini')
            ->assertSee('Listrik bar hari ini')
            ->assertDontSee('Pengeluaran periode lama')
            ->assertSee('Keluar Rp 3.000');
    }

    public function test_barista_menu_page_only_shows_active_menu_items(): void
    {
        $baristaId = $this->createBarista();
        $categoryId = DB::table('menu_categories')->insertGetId([
            'name' => 'Coffee',
            'description' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('menu_items')->insert([
            [
                'category_id' => $categoryId,
                'name' => 'Active Espresso',
                'price' => 18000,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category_id' => $categoryId,
                'name' => 'Hidden Espresso',
                'price' => 19000,
                'active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/barista/menu')
            ->assertOk()
            ->assertSee('data-live-sync="owner-menu-management"', false)
            ->assertSee('Active Espresso')
            ->assertSee('Aktif')
            ->assertSee('Hidden Espresso')
            ->assertSee('Nonaktif');

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/barista/operational')
            ->assertOk()
            ->assertSee('data-live-sync="barista-operational-menu-list"', false)
            ->assertSee('Active Espresso')
            ->assertDontSee('Hidden Espresso');
    }

    public function test_owner_menu_status_changes_are_visible_to_waiter_and_barista(): void
    {
        $waiterId = $this->createWaiter();
        $baristaId = $this->createBarista();
        $menuId = $this->createMenuItem();

        $this->withSession(['owner_logged_in' => true])
            ->post('/owner/menu/'.$menuId.'/toggle')
            ->assertRedirect('/owner/menu?tab=menu');

        $this->withSession(['owner_logged_in' => true])
            ->get('/owner/menu')
            ->assertOk()
            ->assertSee('data-live-sync="owner-menu-management"', false)
            ->assertSee('Espresso')
            ->assertSee('Nonaktif');

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->get('/waiter/orders')
            ->assertOk()
            ->assertDontSee('Espresso');

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/barista/menu')
            ->assertOk()
            ->assertSee('Espresso')
            ->assertSee('Nonaktif');

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/barista/operational')
            ->assertOk()
            ->assertDontSee('Espresso');

        $this->withSession(['owner_logged_in' => true])
            ->post('/owner/menu/'.$menuId.'/toggle')
            ->assertRedirect('/owner/menu?tab=menu');

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->get('/waiter/orders')
            ->assertOk()
            ->assertSee('Espresso');

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/barista/menu')
            ->assertOk()
            ->assertSee('Espresso');

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/barista/operational')
            ->assertOk()
            ->assertSee('Espresso');

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/barista/menu')
            ->assertOk()
            ->assertSee('Aktif');
    }

    public function test_barista_can_toggle_menu_status_and_owner_and_waiter_see_shared_status(): void
    {
        $waiterId = $this->createWaiter();
        $baristaId = $this->createBarista();
        $menuId = $this->createMenuItem();

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/barista/menu')
            ->assertOk()
            ->assertSee('aria-label="toggle menu Espresso"', false);

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->post('/barista/menu/'.$menuId.'/status', ['active' => 0])
            ->assertRedirect('/barista/menu')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('menu_items', ['id' => $menuId, 'active' => 0]);

        $this->withSession(['owner_logged_in' => true])
            ->get('/owner/menu')
            ->assertOk()
            ->assertSee('Espresso')
            ->assertSee('Nonaktif');

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->get('/waiter/orders')
            ->assertOk()
            ->assertDontSee('Espresso');

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/barista/menu')
            ->assertOk()
            ->assertSee('Nonaktif')
            ->assertSee('aria-label="toggle menu Espresso"', false);

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->post('/barista/menu/'.$menuId.'/status', ['active' => 1])
            ->assertRedirect('/barista/menu');

        $this->assertDatabaseHas('menu_items', ['id' => $menuId, 'active' => 1]);

        $this->withSession(['owner_logged_in' => true])
            ->get('/owner/menu')
            ->assertOk()
            ->assertSee('Espresso')
            ->assertSee('Aktif');

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->get('/waiter/orders')
            ->assertOk()
            ->assertSee('Espresso');
    }

    public function test_non_barista_cannot_change_menu_status(): void
    {
        $waiterId = $this->createWaiter();
        $menuId = $this->createMenuItem();

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->post('/barista/menu/'.$menuId.'/status', ['active' => 0])
            ->assertRedirect('/login');

        $this->assertDatabaseHas('menu_items', ['id' => $menuId, 'active' => 1]);
    }

    public function test_barista_can_manage_shared_menu_and_categories_with_owner_management_page(): void
    {
        $baristaId = $this->createBarista();
        $categoryId = DB::table('menu_categories')->insertGetId([
            'name' => 'Coffee',
            'description' => 'Menu kopi',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/barista/menu')
            ->assertOk()
            ->assertSee('Menu Items')
            ->assertSee('Categories')
            ->assertSee('Tambah Menu Item')
            ->assertSee('data-live-sync="owner-menu-management"', false)
            ->assertSee('/owner/menu', false);

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->get('/barista/menu?tab=categories')
            ->assertOk()
            ->assertSee('Tambah Kategori')
            ->assertSee('data-live-sync="owner-menu-categories"', false)
            ->assertSee('/owner/categories', false);

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->post('/owner/categories', [
                'name' => 'Tea',
                'description' => 'Menu teh',
            ])
            ->assertRedirect('/barista/menu?tab=categories');

        $newCategoryId = (int) DB::table('menu_categories')->where('name', 'Tea')->value('id');

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->post('/owner/menu', [
                'name' => 'Matcha',
                'price' => 25000,
                'category_id' => $newCategoryId,
            ])
            ->assertRedirect('/barista/menu?tab=menu');

        $menuId = (int) DB::table('menu_items')->where('name', 'Matcha')->value('id');

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->post('/owner/menu/'.$menuId.'/update', [
                'name' => 'Iced Matcha',
                'price' => 27000,
                'category_id' => $newCategoryId,
            ])
            ->assertRedirect('/barista/menu?tab=menu');

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->post('/owner/menu/'.$menuId.'/toggle')
            ->assertRedirect('/barista/menu?tab=menu');

        $this->assertDatabaseHas('menu_items', [
            'id' => $menuId,
            'name' => 'Iced Matcha',
            'price' => 27000,
            'active' => 0,
        ]);

        $this->withSession(['owner_logged_in' => true])
            ->get('/owner/menu')
            ->assertOk()
            ->assertSee('Iced Matcha')
            ->assertSee('Nonaktif')
            ->assertSee('Tea');

        $this->withSession(['owner_logged_in' => false, 'barista_logged_in' => true, 'barista_id' => $baristaId])
            ->post('/owner/menu/'.$menuId.'/delete')
            ->assertRedirect('/barista/menu?tab=menu');

        $this->assertDatabaseMissing('menu_items', ['id' => $menuId]);

        $this->withSession(['owner_logged_in' => false, 'barista_logged_in' => true, 'barista_id' => $baristaId])
            ->post('/owner/categories/'.$newCategoryId.'/update', [
                'name' => 'Matcha',
                'description' => 'Green tea',
            ])
            ->assertRedirect('/barista/menu?tab=categories');

        $this->withSession(['owner_logged_in' => false, 'barista_logged_in' => true, 'barista_id' => $baristaId])
            ->post('/owner/categories/'.$newCategoryId.'/delete')
            ->assertRedirect('/barista/menu?tab=categories');

        $this->assertDatabaseMissing('menu_categories', ['id' => $newCategoryId]);
        $this->assertDatabaseHas('menu_categories', ['id' => $categoryId, 'name' => 'Coffee']);
    }

    public function test_non_barista_cannot_use_owner_menu_management_actions(): void
    {
        $waiterId = $this->createWaiter();

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiterId])
            ->post('/owner/categories', ['name' => 'Unauthorized'])
            ->assertRedirect('/login');

        $baristaId = $this->createBarista();
        $this->withSession([
            'owner_logged_in' => false,
            'waiter_logged_in' => false,
            'barista_logged_in' => true,
            'barista_id' => $baristaId,
        ])
            ->get('/owner/dashboard')
            ->assertRedirect('/login');

        DB::table('staff_members')->where('id', $baristaId)->update(['active' => false]);

        $this->withSession(['barista_logged_in' => true, 'barista_id' => $baristaId])
            ->post('/owner/categories', ['name' => 'Unauthorized'])
            ->assertRedirect('/login');

        $this->assertDatabaseMissing('menu_categories', ['name' => 'Unauthorized']);
    }

    public function test_non_barista_staff_cannot_enter_barista_account(): void
    {
        DB::table('staff_members')->insert([
            'name' => 'Waiter Gotham',
            'email' => 'waiter@example.test',
            'position' => 'Waiter',
            'password' => Hash::make('waiter-pass-123'),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->from('/login')->post('/login', [
            'email' => 'waiter@example.test',
            'password' => 'waiter-pass-123',
        ])->assertRedirect('/waiter/orders');

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => 1])
            ->get('/barista/operational')
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

    private function createMenuItem(): int
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
            'active' => true,
            'description' => 'Coffee',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createOrder(int $staffMemberId, int $menuItemId): int
    {
        $orderId = DB::table('orders')->insertGetId([
            'staff_member_id' => $staffMemberId,
            'waiter_name' => 'Waiter Gotham',
            'customer_name' => 'Pelanggan',
            'status' => 'completed',
            'total_amount' => 18000,
            'payment_method' => 'cash',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'menu_item_id' => $menuItemId,
            'menu_name' => 'Espresso',
            'unit_price' => 18000,
            'quantity' => 1,
            'line_total' => 18000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $orderId;
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
}
