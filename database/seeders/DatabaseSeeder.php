<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $coffee = \Illuminate\Support\Facades\DB::table('menu_categories')->updateOrInsert(
            ['name' => 'Coffee'],
            ['description' => 'Menu kopi khas', 'created_at' => now(), 'updated_at' => now()]
        );

        \Illuminate\Support\Facades\DB::table('menu_categories')->updateOrInsert(
            ['name' => 'Food'],
            ['description' => 'Makanan pendamping', 'created_at' => now(), 'updated_at' => now()]
        );

        \Illuminate\Support\Facades\DB::table('menu_categories')->updateOrInsert(
            ['name' => 'Non Coffee'],
            ['description' => 'Minuman non kopi', 'created_at' => now(), 'updated_at' => now()]
        );

        $coffeeId = \Illuminate\Support\Facades\DB::table('menu_categories')->where('name', 'Coffee')->value('id');
        $foodId = \Illuminate\Support\Facades\DB::table('menu_categories')->where('name', 'Food')->value('id');
        $nonCoffeeId = \Illuminate\Support\Facades\DB::table('menu_categories')->where('name', 'Non Coffee')->value('id');

        foreach ([
            ['category_id' => $coffeeId, 'name' => 'Espresso', 'price' => 18000, 'description' => 'Coffee'],
            ['category_id' => $coffeeId, 'name' => 'Cappuccino', 'price' => 25000, 'description' => 'Coffee'],
            ['category_id' => $nonCoffeeId, 'name' => 'Lychee Tea', 'price' => 22000, 'description' => 'Non Coffee'],
            ['category_id' => $foodId, 'name' => 'Toast Sandwich', 'price' => 32000, 'description' => 'Food'],
            ['category_id' => $coffeeId, 'name' => 'Sanger', 'price' => 18000, 'description' => 'Coffee'],
            ['category_id' => $coffeeId, 'name' => 'Vanilla Latte', 'price' => 22000, 'description' => 'Coffee'],
        ] as $item) {
            \Illuminate\Support\Facades\DB::table('menu_items')->updateOrInsert(
                ['category_id' => $item['category_id'], 'name' => $item['name']],
                [
                    'price' => $item['price'],
                    'active' => true,
                    'description' => $item['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
