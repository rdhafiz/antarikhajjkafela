<?php

namespace App\Domain\Access;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the permissions and default roles declared in config/access.php.
 * Safe to run repeatedly: it only adds what is missing.
 */
class AccessSynchronizer
{
    /**
     * @return array{permissions: int, roles: int}
     */
    public function sync(): array
    {
        $guard = config('access.guard');
        $created = ['permissions' => 0, 'roles' => 0];

        foreach (self::permissionNames() as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
            $created['permissions'] += (int) $permission->wasRecentlyCreated;
        }

        foreach (config('access.roles') as $name => $definition) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => $guard]);

            if ($role->wasRecentlyCreated) {
                $created['roles']++;
                $role->givePermissionTo($this->grantedPermissions($definition['grants']));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $created;
    }

    /**
     * @return list<string>
     */
    public static function permissionNames(): array
    {
        $names = [];

        foreach (config('access.modules') as $module => $definition) {
            foreach ($definition['actions'] as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }

    /**
     * @param  array<string, string|list<string>>  $grants
     * @return list<string>
     */
    private function grantedPermissions(array $grants): array
    {
        $names = [];

        foreach ($grants as $module => $actions) {
            $actions = $actions === '*' ? config("access.modules.{$module}.actions", []) : $actions;

            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }
}
