<?php

namespace App\Repositories;

use Spatie\Permission\Models\Permission;
use Illuminate\Pagination\LengthAwarePaginator;

class PermissionRepository
{
    /**
     * Get filtered permissions with pagination.
     *
     * @return LengthAwarePaginator<int, Permission>
     */
    public function getFilteredPermissions(array $filters = []): LengthAwarePaginator
    {
        $query = Permission::query();

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (isset($filters['guard_name'])) {
            $query->where('guard_name', $filters['guard_name']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
