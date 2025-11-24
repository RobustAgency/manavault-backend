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

        // Filter by voucher_id
        if (isset($filters['voucher_id'])) {
            $query->where('voucher_id', $filters['voucher_id']);
        }

        // Filter by user_id
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filter by action
        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        // Filter by date range
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
