<?php

namespace App\Repositories;

use App\Models\Module;
use Illuminate\Pagination\LengthAwarePaginator;

class ModuleRepository
{
    /**
     * Get filtered modules with their permissions and pagination.
     *
     * @return LengthAwarePaginator<int, Module>
     */
    public function getFilteredModules(array $filters = []): LengthAwarePaginator
    {
        $query = Module::with('permissions');

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (isset($filters['slug'])) {
            $query->where('slug', $filters['slug']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
}
