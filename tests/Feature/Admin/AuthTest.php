<?php

namespace Tests\Feature\Admin;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

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

    public function test_forgot_password_does_not_reveal_whether_the_account_exists(): void
    {
        Notification::fake();
        $user = $this->superAdmin(['email' => 'owner@example.com']);

        $known = $this->postJson('/api/admin/auth/forgot-password', ['email' => 'owner@example.com'])->assertOk()->json('message');
        $unknown = $this->postJson('/api/admin/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()->json('message');

        $this->assertSame($known, $unknown);
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_contains($url, '/admin/reset-password?token=');
        });
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        $user = $this->superAdmin(['email' => 'owner@example.com']);
        $token = Password::createToken($user);

        $this->postJson('/api/admin/auth/reset-password', [
            'token' => $token,
            'email' => 'owner@example.com',
            'password' => 'BrandNew12345',
            'password_confirmation' => 'BrandNew12345',
        ])->assertOk();

        $this->assertTrue(auth('web')->validate(['email' => 'owner@example.com', 'password' => 'BrandNew12345']));
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $user = $this->superAdmin();
        $token = Password::createToken($user);

        $this->postJson('/api/admin/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
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
        $this->get('/admin')->assertOk()->assertSee('id="admin"', false)->assertSee('--admin-primary: #012a51', false);
        $this->get('/admin/users/anything')->assertOk();
    }
}
