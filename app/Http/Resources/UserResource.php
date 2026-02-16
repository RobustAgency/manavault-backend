<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_approved' => $this->is_approved,
            'role' => $this->roles->first()->name ?? null,
            'modules' => $this->getPermissionsByModule(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    /**
     * Group permissions by module.
     *
     * @return array<int, array{id: int, name: string, slug: string, permissions: array<string>}>
     */
    private function getPermissionsByModule(): array
    {
        $permissions = $this->getAllPermissions();
        $grouped = [];

        foreach ($permissions as $permission) {
            $moduleId = $permission->module_id;
            $moduleName = $permission->module->name;
            $moduleSlug = $permission->module->slug;
            $permissionName = $permission->name;

            if (! isset($grouped[$moduleId])) {
                $grouped[$moduleId] = [
                    'id' => $moduleId,
                    'name' => $moduleName,
                    'slug' => $moduleSlug,
                    'permissions' => [],
                ];
            }

            $grouped[$moduleId]['permissions'][] = $permissionName;
        }

        return array_values($grouped);
    }
}
