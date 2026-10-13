<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_staff_member(): void
    {
        $response = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/staff', [
                'name' => 'Ayu Barista',
                'email' => 'ayu@example.test',
                'position' => 'Barista',
                'password' => 'barista-pass-123',
            ]);

        $response->assertRedirect('/owner/staff');
        $this->assertDatabaseHas('staff_members', [
            'name' => 'Ayu Barista',
            'email' => 'ayu@example.test',
            'position' => 'Barista',
            'active' => 1,
        ]);
        $this->assertTrue(Hash::check('barista-pass-123', DB::table('staff_members')->where('email', 'ayu@example.test')->value('password')));
    }

    public function test_owner_can_update_staff_member(): void
    {
        $staffId = DB::table('staff_members')->insertGetId([
            'name' => 'Ayu Barista',
            'email' => 'ayu@example.test',
            'position' => 'Barista',
            'password' => Hash::make('barista-pass-123'),
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/staff/'.$staffId.'/update', [
                'name' => 'Ayu Putri',
                'email' => 'ayu.putri@example.test',
                'position' => 'Supervisor',
                'password' => 'supervisor-pass-456',
            ]);

        $response->assertRedirect('/owner/staff');
        $this->assertDatabaseHas('staff_members', [
            'id' => $staffId,
            'name' => 'Ayu Putri',
            'email' => 'ayu.putri@example.test',
            'position' => 'Supervisor',
        ]);
        $this->assertTrue(Hash::check('supervisor-pass-456', DB::table('staff_members')->where('id', $staffId)->value('password')));
    }

    public function test_owner_can_toggle_and_delete_staff_member(): void
    {
        $staffId = DB::table('staff_members')->insertGetId([
            'name' => 'Budi Waiter',
            'email' => 'budi@example.test',
            'position' => 'Waiter',
            'password' => Hash::make('waiter-pass-123'),
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $toggleResponse = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/staff/'.$staffId.'/toggle');

        $toggleResponse->assertRedirect('/owner/staff');
        $this->assertDatabaseHas('staff_members', ['id' => $staffId, 'active' => 0]);

        $deleteResponse = $this->withSession(['owner_logged_in' => true])
            ->post('/owner/staff/'.$staffId.'/delete');

        $deleteResponse->assertRedirect('/owner/staff');
        $this->assertDatabaseMissing('staff_members', ['id' => $staffId]);
    }

    public function test_unauthenticated_user_cannot_manage_staff(): void
    {
        $this->get('/owner/staff')->assertRedirect('/login');

        $this->post('/owner/staff', [
            'name' => 'Ayu Barista',
            'email' => 'ayu@example.test',
            'position' => 'Barista',
            'password' => 'barista-pass-123',
        ])->assertRedirect('/login');
    }
}
