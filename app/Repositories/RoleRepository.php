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

        if (isset($filters['group_id'])) {
            $query->where('group_id', $filters['group_id']);
        }

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->with('permissions')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function createRole(array $data): Role
    {
        // Set the Spatie Team Context
        setPermissionsTeamId($data['group_id']);

        $guardName = $data['guard_name'] ?? 'api';

        // We create the role specifically for this group context
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => $guardName,
            'group_id' => $data['group_id'],
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
        return $role->delete();
    }
}
