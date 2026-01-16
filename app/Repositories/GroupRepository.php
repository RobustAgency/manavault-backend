<?php

namespace App\Repositories;

use App\Models\Group;
use Illuminate\Pagination\LengthAwarePaginator;

class GroupRepository
{
    /**
     * Get paginated groups filtered by the provided criteria.
     *
     * @return LengthAwarePaginator<int, Group>
     */
    public function getFilteredGroups(array $filters = []): LengthAwarePaginator
    {
        $query = Group::query();

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('name', 'asc')->paginate($perPage);
    }

    /**
     * Create a new group
     */
    public function createGroup(array $data): Group
    {
        return Group::create($data);
    }

    /**
     * Update the specified group
     */
    public function updateGroup(Group $group, array $data): Group
    {
        $group->update($data);

        return $group->refresh();
    }

    /**
     * Delete the specified group
     */
    public function deleteGroup(Group $group): bool
    {
        return $group->delete();
    }
}
