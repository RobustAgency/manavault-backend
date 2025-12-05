<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Repositories\ActivityLogRepository;
use App\Http\Requests\ActivityLogs\ListActivityLogRequest;

class ActivityLogController extends Controller
{
    public function __construct(private ActivityLogRepository $activityLogRepository) {}

    public function index(ListActivityLogRequest $request): JsonResponse
    {
        $activityLogs = $this->activityLogRepository->getFilteredActivityLogs($request->validated());

        return response()->json([
            'error' => false,
            'data' => $activityLogs,
            'message' => 'Activity logs retrieved successfully.',
        ]);
    }
}
