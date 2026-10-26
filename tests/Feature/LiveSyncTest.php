<?php

namespace Tests\Feature;

use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LiveSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_endpoints_require_authentication(): void
    {
        $this->getJson('/live-sync')->assertUnauthorized();
        $this->getJson('/api/v1/sync')->assertUnauthorized();
    }

    public function test_web_changes_update_the_same_revision_used_by_mobile(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $before = $this->getJson('/api/v1/sync')->assertOk()->json('data.revision');

        $this->withSession(['owner_logged_in' => true])->post('/owner/staff', [
            'name' => 'Dina', 'email' => 'dina@example.test', 'position' => 'Waiter', 'password' => 'password123',
        ])->assertRedirect('/owner/staff');

        $this->assertDatabaseHas('staff_members', ['email' => 'dina@example.test']);
        $after = $this->getJson('/api/v1/sync')->assertOk()->json('data.revision');
        $this->assertNotSame($before, $after);
        $this->getJson('/live-sync')->assertOk()->assertJsonPath('data.revision', $after)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/v1/sync')->assertJsonPath('data.revision', $after);
    }

    public function test_api_menu_creation_notifies_web_and_does_not_change_revision_on_reads(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $before = $this->withSession(['owner_logged_in' => true])->getJson('/live-sync')->json('data.revision');
        $category = DB::table('menu_categories')->insertGetId(['name' => 'Coffee', 'created_at' => now(), 'updated_at' => now()]);

        $this->postJson('/api/v1/menu-items', ['name' => 'Live Latte', 'price' => 22000, 'category_id' => $category])->assertCreated();

        $after = $this->getJson('/live-sync')->assertOk()->json('data.revision');
        $this->assertNotSame($before, $after);
        $this->get('/owner/menu')->assertOk()->assertSee('Live Latte');
        $this->getJson('/api/v1/sync')->assertJsonPath('data.revision', $after);
    }

    public function test_web_waiter_order_notifies_mobile_owner_and_updates_dashboard(): void
    {
        $waiter = $this->waiter();
        Sanctum::actingAs(User::factory()->create());
        $category = DB::table('menu_categories')->insertGetId(['name' => 'Coffee', 'created_at' => now(), 'updated_at' => now()]);
        $menu = DB::table('menu_items')->insertGetId(['name' => 'Latte', 'price' => 22000, 'category_id' => $category, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $before = $this->getJson('/api/v1/sync')->json('data.revision');

        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiter->id])->post('/waiter/orders', [
            'customer_name' => 'Bruce', 'items' => [$menu => 2],
        ])->assertRedirect('/waiter/orders');

        $this->assertDatabaseHas('orders', ['customer_name' => 'Bruce', 'total_amount' => 44000]);
        $after = $this->getJson('/api/v1/sync')->assertOk()->json('data.revision');
        $this->assertNotSame($before, $after);
        $this->getJson('/api/v1/owner/dashboard')->assertOk()->assertJsonPath('data.total_orders', 1)->assertJsonPath('data.active_orders', 1);
    }

    public function test_failed_api_mutations_do_not_notify_other_clients(): void
    {
        Sanctum::actingAs($this->waiter());
        $before = $this->getJson('/api/v1/sync')->json('data.revision');
        $this->postJson('/api/v1/menu-items', ['name' => 'Forbidden'])->assertForbidden();
        $this->postJson('/api/v1/orders', ['items' => []])->assertUnprocessable();
        $this->getJson('/api/v1/sync')->assertJsonPath('data.revision', $before);
    }

    public function test_inactive_staff_cannot_subscribe_through_web_or_api(): void
    {
        $waiter = $this->waiter();
        $waiter->update(['active' => false]);
        Sanctum::actingAs($waiter);
        $this->getJson('/api/v1/sync')->assertUnauthorized();
        $this->withSession(['waiter_logged_in' => true, 'waiter_id' => $waiter->id])->getJson('/live-sync')->assertUnauthorized();
    }

    private function waiter(): StaffMember
    {
        $id = DB::table('staff_members')->insertGetId([
            'name' => 'Waiter', 'email' => 'waiter@example.test', 'position' => 'Waiter',
            'password' => Hash::make('password123'), 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return StaffMember::query()->findOrFail($id);
    }
}
