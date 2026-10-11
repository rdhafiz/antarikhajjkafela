<?php

namespace Tests\Feature\Admin;

use App\Models\User;

class UserManagementTest extends AdminTestCase
{
    public function test_users_can_be_listed_and_searched(): void
    {
        $admin = $this->superAdmin(['name' => 'Owner']);
        User::factory()->create(['name' => 'Rahim Uddin']);

        $this->asAdmin($admin)->getJson('/api/admin/users?search=Rahim')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Rahim Uddin');
    }

    public function test_permissions_are_enforced(): void
    {
        $viewer = $this->adminWith(['users.view']);

        $this->asAdmin($viewer)->getJson('/api/admin/users')->assertOk();
        $this->asAdmin($viewer)->postJson('/api/admin/users', [])->assertForbidden();
        $this->asAdmin($viewer)->getJson('/api/admin/roles')->assertForbidden();
    }

    public function test_a_user_can_be_created_with_roles(): void
    {
        $admin = $this->superAdmin();

        $this->asAdmin($admin)->postJson('/api/admin/users', [
            'name' => 'Booking Desk',
            'email' => 'desk@example.com',
            'password' => 'Desk1234567',
            'password_confirmation' => 'Desk1234567',
            'is_active' => true,
            'roles' => ['booking-manager'],
        ])->assertCreated()->assertJsonPath('data.roles', ['booking-manager']);

        $this->assertTrue(User::whereEmail('desk@example.com')->first()->can('bookings.view'));
    }

    public function test_only_super_admins_can_grant_the_super_admin_role(): void
    {
        $manager = $this->adminWith(['users.view', 'users.create', 'users.update']);

        $this->asAdmin($manager)->postJson('/api/admin/users', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'Sneaky12345',
            'password_confirmation' => 'Sneaky12345',
            'roles' => ['super-admin'],
        ])->assertJsonValidationErrors('roles.0');
    }

    public function test_non_super_admins_cannot_edit_a_super_admin(): void
    {
        $owner = $this->superAdmin();
        $manager = $this->adminWith(['users.view', 'users.update', 'users.delete']);

        $this->asAdmin($manager)->putJson("/api/admin/users/{$owner->id}", [
            'name' => 'Hijacked',
            'email' => $owner->email,
            'password' => 'Hijack123456',
            'password_confirmation' => 'Hijack123456',
        ])->assertForbidden();

        $this->asAdmin($manager)->deleteJson("/api/admin/users/{$owner->id}")->assertForbidden();
    }

    public function test_the_last_super_admin_cannot_be_demoted_or_deleted(): void
    {
        $owner = $this->superAdmin();
        $other = $this->superAdmin();
        $other->delete();

        $this->asAdmin($owner)->putJson("/api/admin/users/{$owner->id}", [
            'name' => $owner->name,
            'email' => $owner->email,
            'roles' => ['admin'],
        ])->assertJsonValidationErrors('roles');
    }

    public function test_admins_cannot_delete_or_deactivate_themselves(): void
    {
        $owner = $this->superAdmin();
        $this->superAdmin();

        $this->asAdmin($owner)->deleteJson("/api/admin/users/{$owner->id}")->assertJsonValidationErrors('user');

        $this->asAdmin($owner)->putJson("/api/admin/users/{$owner->id}", [
            'name' => $owner->name,
            'email' => $owner->email,
            'is_active' => false,
        ])->assertJsonValidationErrors('is_active');
    }

    public function test_role_options_hide_super_admin_from_non_super_admins(): void
    {
        $manager = $this->adminWith(['users.view']);

        $names = collect($this->asAdmin($manager)->getJson('/api/admin/users/role-options')->json('data'))->pluck('name');

        $this->assertNotContains('super-admin', $names);
        $this->assertContains('content-editor', $names);
    }

    public function test_custom_roles_can_be_created_updated_and_deleted(): void
    {
        $admin = $this->superAdmin();

        $id = $this->asAdmin($admin)->postJson('/api/admin/roles', ['name' => 'Marketing', 'permissions' => ['campaigns.view']])
            ->assertCreated()
            ->assertJsonPath('data.name', 'marketing')
            ->json('data.id');

        $this->asAdmin($admin)->putJson("/api/admin/roles/{$id}", ['name' => 'Marketing', 'permissions' => ['campaigns.view', 'campaigns.update']])
            ->assertOk()
            ->assertJsonPath('data.permissions', ['campaigns.view', 'campaigns.update']);

        $this->asAdmin($admin)->postJson('/api/admin/roles', ['name' => 'marketing'])->assertJsonValidationErrors('slug');
        $this->asAdmin($admin)->putJson("/api/admin/roles/{$id}", ['name' => 'Marketing', 'permissions' => ['made.up']])->assertJsonValidationErrors('permissions.0');

        $this->asAdmin($admin)->deleteJson("/api/admin/roles/{$id}")->assertNoContent();
    }

    public function test_default_and_super_admin_roles_are_protected(): void
    {
        $admin = $this->superAdmin();
        $roles = collect($this->asAdmin($admin)->getJson('/api/admin/roles')->json('data'))->keyBy('name');

        $this->asAdmin($admin)->putJson("/api/admin/roles/{$roles['super-admin']['id']}", ['name' => 'x', 'permissions' => []])
            ->assertJsonValidationErrors('role');
        $this->asAdmin($admin)->deleteJson("/api/admin/roles/{$roles['admin']['id']}")
            ->assertJsonValidationErrors('role');
    }
}
