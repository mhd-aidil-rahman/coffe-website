<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_menu_category(): void
    {
        $response = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/categories', [
                'name' => 'Coffee',
                'description' => 'Menu kopi khas',
            ]);

        $response->assertRedirect('/owner/menu?tab=categories');
        $this->assertDatabaseHas('menu_categories', [
            'name' => 'Coffee',
            'description' => 'Menu kopi khas',
        ]);
    }

    public function test_owner_can_update_and_delete_unused_category(): void
    {
        $categoryId = DB::table('menu_categories')->insertGetId([
            'name' => 'Coffee',
            'description' => 'Menu kopi khas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $updateResponse = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/categories/'.$categoryId.'/update', [
                'name' => 'Hot Drinks',
                'description' => 'Minuman hangat',
            ]);

        $updateResponse->assertRedirect('/owner/menu?tab=categories');
        $this->assertDatabaseHas('menu_categories', [
            'id' => $categoryId,
            'name' => 'Hot Drinks',
            'description' => 'Minuman hangat',
        ]);

        $deleteResponse = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/categories/'.$categoryId.'/delete');

        $deleteResponse->assertRedirect('/owner/menu?tab=categories');
        $this->assertDatabaseMissing('menu_categories', ['id' => $categoryId]);
    }

    public function test_owner_cannot_delete_category_used_by_menu_item(): void
    {
        $categoryId = DB::table('menu_categories')->insertGetId([
            'name' => 'Coffee',
            'description' => 'Menu kopi khas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'Espresso',
            'price' => 18000,
            'active' => 1,
            'description' => 'Coffee classic',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/categories/'.$categoryId.'/delete');

        $response->assertRedirect('/owner/menu?tab=categories');
        $response->assertSessionHasErrors('category');
        $this->assertDatabaseHas('menu_categories', ['id' => $categoryId]);
        $this->assertDatabaseHas('menu_items', ['id' => $menuId, 'category_id' => $categoryId]);
    }

    public function test_owner_can_create_menu_item(): void
    {
        $categoryId = DB::table('menu_categories')->insertGetId([
            'name' => 'Coffee',
            'description' => 'Menu kopi khas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/menu', [
                'name' => 'Espresso',
                'price' => '18000',
                'category_id' => $categoryId,
                'active' => 1,
            ]);

        $response->assertRedirect('/owner/menu?tab=menu');
        $this->assertDatabaseHas('menu_items', [
            'name' => 'Espresso',
            'price' => 18000,
            'category_id' => $categoryId,
            'active' => 1,
        ]);
    }

    public function test_owner_can_toggle_menu_item_status(): void
    {
        $categoryId = DB::table('menu_categories')->insertGetId([
            'name' => 'Coffee',
            'description' => 'Menu kopi khas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'Latte',
            'price' => 22000,
            'active' => 1,
            'description' => 'Cappuccino latte',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/menu/'.$menuId.'/toggle');

        $response->assertRedirect('/owner/menu?tab=menu');
        $this->assertDatabaseHas('menu_items', [
            'id' => $menuId,
            'active' => 0,
        ]);
    }

    public function test_owner_can_update_and_delete_menu_item_with_image_upload(): void
    {
        $categoryId = DB::table('menu_categories')->insertGetId([
            'name' => 'Coffee',
            'description' => 'Menu kopi khas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $menuId = DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'Espresso',
            'price' => 18000,
            'active' => 1,
            'description' => 'Coffee classic',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $image = UploadedFile::fake()->image('espresso.jpg');

        $response = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/menu/'.$menuId.'/update', [
                'name' => 'Espresso Signature',
                'price' => '21000',
                'description' => 'Coffe with crema',
                'category_id' => $categoryId,
                'image' => $image,
            ]);

        $response->assertRedirect('/owner/menu?tab=menu');
        $this->assertDatabaseHas('menu_items', [
            'id' => $menuId,
            'name' => 'Espresso Signature',
            'price' => 21000,
        ]);

        $menu = DB::table('menu_items')->where('id', $menuId)->first();
        $this->assertNotNull($menu->image_path);
        $this->assertStringContainsString('espresso', $menu->image_path);

        $deleteResponse = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/menu/'.$menuId.'/delete');

        $deleteResponse->assertRedirect('/owner/menu?tab=menu');
        $this->assertDatabaseMissing('menu_items', ['id' => $menuId]);
    }
}
