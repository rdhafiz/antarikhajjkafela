<?php

namespace App\Http\Resources\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class CurrentUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_super_admin' => $this->isSuperAdmin(),
            'roles' => $this->getRoleNames(),
            'permissions' => $this->isSuperAdmin()
                ? ['*']
                : $this->getAllPermissions()->pluck('name')->values(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
        ];
    }
}
