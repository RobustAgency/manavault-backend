<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository
{
    /**
     * Search and get paginated users.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, User>
     */
    public function searchPaginated(array $filters = []): LengthAwarePaginator
    {
        $query = User::query()
            ->with(['roles'])
            ->where('role', '!=', 'super_admin');

        if ($term = $filters['term'] ?? null) {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            });
        }

        return $query->latest()->paginate($filters['per_page'] ?? 10);
    }

    /**
     * Get a user by ID with specified relations.
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }
}
