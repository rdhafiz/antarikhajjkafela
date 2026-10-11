<?php

namespace App\Console\Commands;

use App\Domain\Access\AccessSynchronizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('access:sync')]
#[Description('Create missing admin permissions and default roles')]
class SyncAccess extends Command
{
    public function handle(AccessSynchronizer $synchronizer): int
    {
        $created = $synchronizer->sync();

        $this->components->info("Permissions created: {$created['permissions']}, roles created: {$created['roles']}.");

        return self::SUCCESS;
    }
}
