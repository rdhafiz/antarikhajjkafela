<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\AccessSynchronizer;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RoleResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $roles = Role::query()->with('permissions')->orderBy('id')->get();

        return RoleResource::collection($this->withUserCounts($roles));
    }

    /**
     * The permission matrix shown in the role editor.
     */
    public function permissions(): JsonResponse
    {
        $modules = collect(config('access.modules'))->map(fn ($module, $key) => [
            'key' => $key,
            'label' => $module['label'],
            'actions' => $module['actions'],
        ])->values();

        return response()->json(['data' => $modules]);
    }

    public function store(Request $request): RoleResource
    {
        $data = $this->validated($request);

        $role = Role::create([
            'name' => Str::slug($data['name']),
            'label' => $data['name'],
            'guard_name' => config('access.guard'),
        ]);
        $role->syncPermissions($data['permissions'] ?? []);

        return new RoleResource($this->withUserCounts(new Collection([$role->load('permissions')]))->first());
    }

    public function update(Request $request, Role $role): RoleResource
    {
        $this->guardSuperAdminRole($role);

        $data = $this->validated($request, $role);

        // Renaming changes only what staff see. The slug in "name" is what
        // config/access.php and the permission checks match on, so it stays.
        if (! array_key_exists($role->name, config('access.roles'))) {
            $role->update(['label' => $data['name']]);
        }

        $role->syncPermissions($data['permissions'] ?? []);

        return new RoleResource($this->withUserCounts(new Collection([$role->load('permissions')]))->first());
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->guardSuperAdminRole($role);

        if (array_key_exists($role->name, config('access.roles'))) {
            throw ValidationException::withMessages(['role' => 'Default roles cannot be deleted.']);
        }

        // Users in the Trash keep their roles for a restore, but do not block this.
        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'This role is still assigned to users.']);
        }

        $role->delete();

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Role $role = null): array
    {
        $request->merge(['slug' => Str::slug((string) $request->input('name'))]);

        $rules = [
            'name' => ['required', 'string', 'max:60', Rule::unique('roles', 'label')->ignore($role?->id)],
            'slug' => ['required'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(AccessSynchronizer::permissionNames())],
        ];

        // The slug is set once and never changes, so it only has to be free
        // when the role is created.
        if ($role === null) {
            $rules['slug'][] = Rule::unique('roles', 'name');
        }

        return $request->validate($rules, [
            'name.unique' => 'A role with this name already exists.',
            'slug.unique' => 'A role with this name already exists.',
            'slug.required' => 'The name must contain letters or numbers.',
        ]);
    }

    /**
     * Attach a users_count to each role. Counted directly on the pivot table
     * because the package's users() relation depends on the request's guard.
     *
     * @param  Collection<int, Role>  $roles
     * @return Collection<int, Role>
     */
    private function withUserCounts(Collection $roles): Collection
    {
        $counts = DB::table(config('permission.table_names.model_has_roles'))
            ->whereIn(app(PermissionRegistrar::class)->pivotRole, $roles->modelKeys())
            ->whereIn(config('permission.column_names.model_morph_key'), User::query()->select('id'))
            ->selectRaw(app(PermissionRegistrar::class)->pivotRole.' as role_id, count(*) as total')
            ->groupBy(app(PermissionRegistrar::class)->pivotRole)
            ->pluck('total', 'role_id');

        return $roles->each(fn (Role $role) => $role->setAttribute('users_count', (int) ($counts[$role->id] ?? 0)));
    }

    private function guardSuperAdminRole(Role $role): void
    {
        if ($role->name === config('access.super_admin_role')) {
            throw ValidationException::withMessages(['role' => 'The super admin role always has full access and cannot be changed.']);
        }
    }
}
