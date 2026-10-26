<?php

namespace Database\Seeders;

use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleAccountSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'owner@gotham.id'],
            ['name' => 'Owner Gotham', 'password' => Hash::make('Gotham123!')],
        );

        foreach (['Barista' => 'barista@gotham.id', 'Waiter' => 'waiters@gotham.id'] as $position => $email) {
            StaffMember::query()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $position.' Gotham',
                    'position' => $position,
                    'password' => Hash::make('Gotham123!'),
                    'active' => true,
                ],
            );
        }
    }
}
