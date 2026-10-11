<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Access\AccessSynchronizer;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\RoleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $roles = Role::query()->with('permissions')->withCount('users')->orderBy('id')->get();

        return RoleResource::collection($roles);
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
            'guard_name' => config('auth.defaults.guard'),
        ]);
        $role->syncPermissions($data['permissions'] ?? []);

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    public function update(Request $request, Role $role): RoleResource
    {
        $this->guardSuperAdminRole($role);

        $data = $this->validated($request, $role);

        if (! array_key_exists($role->name, config('access.roles'))) {
            $role->update(['name' => Str::slug($data['name'])]);
        }

        $role->syncPermissions($data['permissions'] ?? []);

        return new RoleResource($role->load('permissions')->loadCount('users'));
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->guardSuperAdminRole($role);

        if (array_key_exists($role->name, config('access.roles'))) {
            throw ValidationException::withMessages(['role' => 'Default roles cannot be deleted.']);
        }

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

        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(AccessSynchronizer::permissionNames())],
        ], [
            'slug.unique' => 'A role with this name already exists.',
            'slug.required' => 'The name must contain letters or numbers.',
        ]);
    }

    private function guardSuperAdminRole(Role $role): void
    {
        if ($role->name === config('access.super_admin_role')) {
            throw ValidationException::withMessages(['role' => 'The super admin role always has full access and cannot be changed.']);
        }
    }
}
