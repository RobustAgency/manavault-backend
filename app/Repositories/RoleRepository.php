<?php

namespace App\Repositories;

use Spatie\Permission\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleRepository
{
    /**
     * Get filtered roles with pagination.
     *
     * @return LengthAwarePaginator<int, Role>
     */
    public function getFilteredRoles(array $filters = []): LengthAwarePaginator
    {
        $query = Role::with('permissions');

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (isset($filters['guard_name'])) {
            $query->where('guard_name', $filters['guard_name']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->with('permissions')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function createRole(array $data): Role
    {

        $guardName = $data['guard_name'] ?? 'api';

        // We create the role specifically for this group context
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => $guardName,
        ]);

        if (isset($data['permission_ids'])) {
            $role->syncPermissions($data['permission_ids']);
        }

        return Role::find($role->id);
    }

    public function updateRole(Role $role, array $data): Role
    {
        if (isset($data['name'])) {
            $role->name = $data['name'];
        }

        $role->save();

        if (isset($data['permission_ids'])) {
            $role->syncPermissions($data['permission_ids']);
        }

        return $role->load('permissions');
    }

    public function deleteRole(Role $role): bool
    {
        $role->loadMissing('permissions');

        $role->permissions()->detach();

        return (bool) $role->delete();
    }
}
