<?php

namespace App\Repositories;

use App\Models\VoucherAuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VoucherAuditLogRepository
{
    /**
     * Get filtered audit logs with pagination
     *
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, VoucherAuditLog>
     */
    public function getFilteredLogs(array $filters): LengthAwarePaginator
    {
        $query = VoucherAuditLog::query()
            ->with(['voucher', 'user'])
            ->orderBy('created_at', 'desc');

        if (isset($filters['name'])) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', '%'.$filters['name'].'%'));
        }

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->paginate($perPage);
    }
}
