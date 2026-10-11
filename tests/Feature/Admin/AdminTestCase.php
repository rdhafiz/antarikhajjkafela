<?php

namespace Tests\Feature\Admin;

use App\Domain\Access\AccessSynchronizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(AccessSynchronizer::class)->sync();
    }

    protected function superAdmin(array $attributes = []): User
    {
        return User::factory()->create($attributes)->assignRole('super-admin');
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function adminWith(array $permissions): User
    {
        return User::factory()->create()->givePermissionTo($permissions);
    }

    /**
     * Act as a user through the SPA's stateful (session) API.
     */
    protected function asAdmin(User $user): static
    {
        // Guards cache the resolved user between requests within one test.
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web')->withHeaders([
            'Referer' => config('app.url'),
            'Accept' => 'application/json',
        ]);
    }
}
