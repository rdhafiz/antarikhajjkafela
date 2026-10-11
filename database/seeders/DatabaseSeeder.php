<?php

namespace Database\Seeders;

use App\Domain\Access\AccessSynchronizer;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(AccessSynchronizer $access): void
    {
        $access->sync();

        $this->call(MenuSeeder::class);

        // Local development login. Production admins are created with `php artisan admin:create`.
        if (app()->isLocal()) {
            User::firstOrCreate(
                ['email' => 'admin@antarikhajjkafela.test'],
                ['name' => 'Site Admin', 'password' => 'Admin@12345', 'email_verified_at' => now()],
            )->assignRole(config('access.super_admin_role'));
        }
    }
}
