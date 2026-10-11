<?php

namespace App\Console\Commands;

use App\Domain\Access\AccessSynchronizer;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('admin:create {--name=} {--email=}')]
#[Description('Create a super admin account')]
class CreateAdmin extends Command
{
    public function handle(AccessSynchronizer $synchronizer): int
    {
        $synchronizer->sync();

        $name = $this->option('name') ?: text('Name', required: true);
        $email = $this->option('email') ?: text('Email', required: true);
        $secret = password('Password (min. 10 characters)', required: true);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $secret],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'unique:users,email'],
                'password' => ['required', Password::min(10)->letters()->numbers()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create(['name' => $name, 'email' => $email, 'password' => $secret]);
        $user->assignRole(config('access.super_admin_role'));

        $this->components->info("Super admin {$email} created.");

        return self::SUCCESS;
    }
}
