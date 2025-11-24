<?php

namespace App\Repositories;

use App\Models\LoginLog;
use Illuminate\Pagination\LengthAwarePaginator;

class LoginLogsRepository
{
    /**
     * Retrieve filtered login logs with pagination.
     *
     * @return LengthAwarePaginator<int, LoginLog>
     */
    public function getFilteredLoginLogs(array $filters): LengthAwarePaginator
    {
        $query = LoginLog::query();
        if (! empty($filters['email'])) {
            $query->where('email', $filters['email']);
        }

        if (! empty($filters['ip_address'])) {
            $query->where('ip_address', $filters['ip_address']);
        }

        if (! empty($filters['activity'])) {
            $query->where('activity', 'like', '%'.$filters['activity'].'%');
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->latest('id')->paginate($perPage);
    }
}
