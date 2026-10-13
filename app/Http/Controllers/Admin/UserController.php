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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'signed_in' => ['nullable', Rule::in(['week', 'month', 'never'])],
            'sort' => ['nullable', Rule::in(['name', 'newest', 'last_login'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->with('roles')
            ->when($request->string('search')->trim()->value(), function ($query, string $search) {
                // "!" escapes % and _ so they are matched literally (the same in MySQL and SQLite).
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $query->where(fn ($q) => $q->whereRaw("name like ? escape '!'", [$like])->orWhereRaw("email like ? escape '!'", [$like]));
            })
            ->when($request->string('role')->value(), fn ($query, string $role) => $query->role($role))
            ->when($request->input('status'), fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->when($request->input('signed_in'), fn ($query, string $when) => match ($when) {
                'never' => $query->whereNull('last_login_at'),
                'week' => $query->where('last_login_at', '>=', now()->subWeek()),
                default => $query->where('last_login_at', '>=', now()->subMonth()),
            })
            ->when($request->input('sort', 'name'), fn ($query, string $sort) => match ($sort) {
                'newest' => $query->latest('id'),
                'last_login' => $query->orderByRaw('last_login_at is null')->latest('last_login_at'),
                default => $query->orderBy('name'),
            })
            ->paginate($request->integer('per_page', 15));

        return UserResource::collection($users)->additional([
            // Counts for the filter drawer.
            'counts' => [
                'total' => User::count(),
                'active' => User::where('is_active', true)->count(),
                'inactive' => User::where('is_active', false)->count(),
                'never' => User::whereNull('last_login_at')->count(),
                'roles' => DB::table(config('permission.table_names.model_has_roles').' as assigned')
                    ->join(config('permission.table_names.roles').' as r', 'r.id', '=', 'assigned.role_id')
                    ->where('assigned.model_type', (new User)->getMorphClass())
                    ->selectRaw('r.name as role, count(*) as n')
                    ->groupBy('r.name')
                    ->pluck('n', 'role')
                    ->map(fn ($n) => (int) $n),
            ],
        ]);
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
