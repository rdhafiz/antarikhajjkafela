<?php

namespace App\Http\Resources\Admin;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // Built-in roles are named by config; the rest keep what was typed.
            'label' => config("access.roles.{$this->name}.label")
                ?? $this->label
                ?? str($this->name)->replace('-', ' ')->title()->toString(),
            'is_super_admin' => $this->name === config('access.super_admin_role'),
            'is_system' => array_key_exists($this->name, config('access.roles')),
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')),
            'users_count' => $this->whenCounted('users'),
        ];
    }
}
