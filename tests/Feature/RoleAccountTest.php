<?php

namespace Tests\Feature;

use App\Models\StaffMember;
use App\Models\User;
use Database\Seeders\RoleAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccountTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, string, string}> */
    public static function accounts(): array
    {
        return [
            'owner' => ['owner@gotham.id', 'owner', '/owner/dashboard', 'Owner'],
            'barista' => ['barista@gotham.id', 'barista', '/barista/operational', 'Barista'],
            'waiters' => ['waiters@gotham.id', 'waiter', '/waiter/orders', 'Waiter'],
        ];
    }

    #[DataProvider('accounts')]
    public function test_seeded_account_logs_in_and_opens_its_dashboard(string $email, string $sessionRole, string $dashboard, string $role): void
    {
        $this->seed(RoleAccountSeeder::class);

        $this->post('/login', ['email' => $email, 'password' => 'Gotham123!'])
            ->assertRedirect($dashboard)->assertSessionHas($sessionRole.'_logged_in', true);

        $this->get($dashboard)->assertOk();
        $this->get('/')->assertRedirect($dashboard);
        $this->get('/login')->assertRedirect($dashboard);

        foreach (array_diff(['/owner/dashboard', '/barista/operational', '/waiter/orders'], [$dashboard]) as $otherDashboard) {
            $this->get($otherDashboard)->assertRedirect('/login');
        }

        $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'Gotham123!'])
            ->assertCreated()->assertJsonPath('data.user.role', $role);
    }

    public function test_seeder_hashes_passwords_and_preserves_existing_accounts_on_rerun(): void
    {
        $this->seed(RoleAccountSeeder::class);

        $owner = User::query()->where('email', 'owner@gotham.id')->firstOrFail();
        $this->assertTrue(Hash::check('Gotham123!', $owner->password));
        foreach (StaffMember::query()->get() as $staff) {
            $this->assertTrue(Hash::check('Gotham123!', $staff->password));
        }

        $owner->update(['password' => 'ChangedPassword123!']);
        $this->seed(RoleAccountSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('staff_members', 2);
        $this->assertTrue(Hash::check('ChangedPassword123!', $owner->refresh()->password));
    }

    public function test_wrong_password_and_inactive_staff_cannot_log_in(): void
    {
        $this->seed(RoleAccountSeeder::class);
        StaffMember::query()->where('email', 'barista@gotham.id')->update(['active' => false]);

        $this->post('/login', ['email' => 'owner@gotham.id', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email')->assertSessionMissing('owner_logged_in');
        $this->post('/login', ['email' => 'barista@gotham.id', 'password' => 'Gotham123!'])
            ->assertSessionHasErrors('email')->assertSessionMissing('barista_logged_in');
    }

    public function test_configured_plaintext_owner_password_cannot_create_an_account(): void
    {
        config(['owner.email' => 'owner@gotham.id', 'owner.password' => 'LegacyPassword123!']);

        $this->post('/login', ['email' => 'owner@gotham.id', 'password' => 'LegacyPassword123!'])
            ->assertSessionHasErrors('email')->assertSessionMissing('owner_logged_in');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_guests_cannot_open_any_dashboard(): void
    {
        $this->get('/owner/dashboard')->assertRedirect('/login');
        $this->get('/barista/operational')->assertRedirect('/login');
        $this->get('/waiter/orders')->assertRedirect('/login');
    }

    public function test_owner_cannot_create_staff_with_an_unsupported_role(): void
    {
        $this->withSession(['owner_logged_in' => true])->post('/owner/staff', [
            'name' => 'Staff Gotham',
            'email' => 'staff@gotham.id',
            'position' => 'Supervisor',
            'password' => 'Gotham123!',
        ])->assertSessionHasErrors('position');

        $this->assertDatabaseMissing('staff_members', ['email' => 'staff@gotham.id']);
    }

    public function test_logout_clears_role_session(): void
    {
        $this->withSession(['owner_logged_in' => true, 'owner_name' => 'Owner Gotham'])
            ->get('/logout')->assertRedirect('/login')->assertSessionMissing('owner_logged_in');

        $this->get('/owner/dashboard')->assertRedirect('/login');
    }
}
