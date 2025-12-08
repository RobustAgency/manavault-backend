<?php

namespace App\Repositories;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class ActivityLogRepository
{
    /**
     * Get filtered activity logs.
     *
     * @return LengthAwarePaginator<int, ActivityLog>
     */
    public function getFilteredActivityLogs(array $filters): LengthAwarePaginator
    {
        $query = ActivityLog::query();

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->latest()->paginate($perPage);
    }

    public function createActivityLog(Model $model, int $modelID, string $event, ?array $meta = []): void
    {
        ActivityLog::create([
            'user_id' => Auth::user()?->id,
            'event' => $event,
            'model_type' => $model::class,
            'model_id' => $modelID,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->path(),
            'meta' => $meta,
        ]);
    }
}
