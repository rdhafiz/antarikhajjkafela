<?php

namespace Tests\Feature\Admin;

class AuthTest extends AdminTestCase
{
    public function test_admin_can_sign_in_and_load_their_profile(): void
    {
        $user = $this->superAdmin(['email' => 'owner@example.com', 'password' => 'Secret12345']);

        $this->postJson('/api/admin/auth/login', ['email' => 'owner@example.com', 'password' => 'Secret12345'], ['Referer' => config('app.url')])
            ->assertOk()
            ->assertJsonPath('data.email', 'owner@example.com')
            ->assertJsonPath('data.is_super_admin', true)
            ->assertJsonPath('data.permissions', ['*']);

        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->superAdmin(['email' => 'owner@example.com', 'password' => 'Secret12345']);

        $this->postJson('/api/admin/auth/login', ['email' => 'owner@example.com', 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertGuest('web');
    }

    public function test_inactive_admin_cannot_sign_in(): void
    {
        $this->superAdmin(['email' => 'owner@example.com', 'password' => 'Secret12345', 'is_active' => false]);

        $this->postJson('/api/admin/auth/login', ['email' => 'owner@example.com', 'password' => 'Secret12345'])
            ->assertUnprocessable();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->superAdmin(['email' => 'owner@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/admin/auth/login', ['email' => 'owner@example.com', 'password' => 'nope'])->assertUnprocessable();
        }

        $this->postJson('/api/admin/auth/login', ['email' => 'owner@example.com', 'password' => 'nope'])->assertTooManyRequests();
    }

    public function test_deactivated_admin_is_signed_out_on_next_request(): void
    {
        $user = $this->superAdmin(['is_active' => false]);

        $this->asAdmin($user)->getJson('/api/admin/auth/me')->assertForbidden();
    }

    public function test_guests_cannot_reach_admin_endpoints(): void
    {
        $this->getJson('/api/admin/auth/me')->assertUnauthorized();
        $this->getJson('/api/admin/users')->assertUnauthorized();
    }

    public function test_there_is_no_password_reset(): void
    {
        $this->superAdmin(['email' => 'owner@example.com']);

        $this->postJson('/api/admin/auth/forgot-password', ['email' => 'owner@example.com'])->assertNotFound();
        $this->postJson('/api/admin/auth/reset-password', ['email' => 'owner@example.com'])->assertNotFound();
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $user = $this->superAdmin(['password' => 'Secret12345']);

        $this->asAdmin($user)->putJson('/api/admin/profile/password', [
            'current_password' => 'Secret12345',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertJsonValidationErrors('password');
    }

    public function test_profile_password_change_requires_the_current_password(): void
    {
        $user = $this->superAdmin(['password' => 'Secret12345']);

        $this->asAdmin($user)->putJson('/api/admin/profile/password', [
            'current_password' => 'not-it',
            'password' => 'Another12345',
            'password_confirmation' => 'Another12345',
        ])->assertJsonValidationErrors('current_password');

        $this->asAdmin($user)->putJson('/api/admin/profile/password', [
            'current_password' => 'Secret12345',
            'password' => 'Another12345',
            'password_confirmation' => 'Another12345',
        ])->assertOk();
    }

    public function test_the_admin_spa_shell_is_served_for_any_admin_path(): void
    {
        $this->get('/ahk/admin/login')->assertOk()->assertSee('id="admin"', false)->assertSee('--admin-primary: #012a51', false)
            ->assertSee("window.adminBase = '\\/ahk\\/'", false)
            // The tab icon (the bundled brand icon, versioned by content) shows even before a favicon is uploaded.
            ->assertSee('<link rel="icon" type="image/webp" sizes="32x32" href="'.url('images/brand/icon-96.webp').'?v='.substr(md5_file(public_path('images/brand/icon-96.webp')), 0, 8).'">', false);
        $this->get('/ahk/admin/users/anything')->assertOk();
        $this->assertSame(url('/ahk/admin/bookings/12'), admin_url('bookings/12'));

        // The old, guessable address is gone.
        $this->get('/admin')->assertNotFound();
        $this->get('/admin/login')->assertNotFound();
    }
}
