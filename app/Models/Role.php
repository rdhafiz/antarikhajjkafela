<?php

namespace App\Models;

use App\Models\Concerns\Trashable;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Roles go to the Trash like everything else; the package keeps their
 * permissions while they are soft deleted, so a restore brings them back.
 */
class Role extends SpatieRole
{
    use Trashable;

    /** Freed while in the Trash, given back on restore. */
    protected array $releaseOnTrash = ['name' => 'suffix'];
}
