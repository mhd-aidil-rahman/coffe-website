<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['owner.email' => 'owner@gotham', 'owner.password' => 'Admin453']);
    }

    public function test_login_page_hides_credentials_and_contains_an_accessible_password_toggle(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk ke akun Anda')
            ->assertSee('data-password-toggle', false)->assertSee('aria-label="Tampilkan password"', false)
            ->assertDontSee('Akun owner:')->assertDontSee('Akun demo')->assertDontSee('owner@gotham')
            ->assertDontSee('Admin453')->assertDontSee('password123');
    }

    public function test_configured_owner_identifier_without_public_domain_can_log_in_on_web_and_api(): void
    {
        $this->post('/login', ['email' => 'owner@gotham', 'password' => 'Admin453'])
            ->assertRedirect('/owner/dashboard')->assertSessionHas('owner_logged_in', true);
        $this->postJson('/api/v1/auth/login', ['email' => 'owner@gotham', 'password' => 'Admin453'])
            ->assertCreated()->assertJsonPath('data.user.email', 'owner@gotham')->assertJsonPath('data.user.role', 'Owner');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', ['email' => 'owner@gotham']);
    }

    public function test_previous_demo_owner_credentials_are_rejected(): void
    {
        $this->post('/login', ['email' => 'owner@gothamcoffee.id', 'password' => 'password123'])->assertSessionHasErrors('email');
        $this->postJson('/api/v1/auth/login', ['email' => 'owner@gothamcoffee.id', 'password' => 'password123'])->assertUnauthorized();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_owner_identifier_still_requires_the_correct_password(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'owner@gotham', 'password' => 'wrong-password'])->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_owner_email_exception_does_not_allow_invalid_staff_email_addresses(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'staff-invalid-email', 'password' => 'Admin453'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->post('/login', ['email' => 'staff-invalid-email', 'password' => 'Admin453'])->assertSessionHasErrors('email');
    }
}
