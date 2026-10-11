<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Resources\Admin\RoleResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->with('roles')
            ->when($request->string('search')->trim()->value(), function ($query, string $search) {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->when($request->string('role')->value(), fn ($query, string $role) => $query->role($role))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users);
    }

    /**
     * Roles that can be assigned in the user form.
     */
    public function roleOptions(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->orderBy('id')
            ->get()
            ->reject(fn (Role $role) => $role->name === config('access.super_admin_role') && ! $request->user()->isSuperAdmin());

        return response()->json(['data' => RoleResource::collection($roles->values())]);
    }

    public function store(UserRequest $request): UserResource
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->safe()->except('roles'));
            $user->syncRoles($request->input('roles', []));

            return $user;
        });

        return new UserResource($user->load('roles'));
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load('roles'));
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $this->guardSuperAdminTarget($request, $user);

        $data = $request->safe()->except('roles');

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($user->is($request->user()) && array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => 'You cannot deactivate your own account.']);
        }

        DB::transaction(function () use ($request, $user, $data) {
            $user->update($data);

            if ($request->has('roles')) {
                $this->guardLastSuperAdmin($user, $request->input('roles', []));
                $user->syncRoles($request->input('roles', []));
            }
        });

        return new UserResource($user->load('roles'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'You cannot delete your own account.']);
        }

        $this->guardSuperAdminTarget($request, $user);
        $this->guardLastSuperAdmin($user, []);

        $user->delete();

        return response()->json(status: 204);
    }

    /**
     * Only a super admin may change or remove another super admin.
     */
    private function guardSuperAdminTarget(Request $request, User $user): void
    {
        abort_if($user->isSuperAdmin() && ! $request->user()->isSuperAdmin(), 403, 'Only a super admin can manage this account.');
    }

    /**
     * Prevent removing the super admin role from the last super admin.
     *
     * @param  list<string>  $newRoles
     */
    private function guardLastSuperAdmin(User $user, array $newRoles): void
    {
        $superAdmin = config('access.super_admin_role');

        if (! $user->hasRole($superAdmin) || in_array($superAdmin, $newRoles, true)) {
            return;
        }

        if (User::role($superAdmin)->count() <= 1) {
            throw ValidationException::withMessages(['roles' => 'At least one super admin must remain.']);
        }
    }
}
