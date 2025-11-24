<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\VoucherAuditLogResource;
use App\Repositories\VoucherAuditLogRepository;
use App\Http\Requests\ListVoucherAuditLogsRequest;

class VoucherAuditLogController extends Controller
{
    public function __construct(
        private VoucherAuditLogRepository $voucherAuditLogRepository
    ) {}

    /**
     * Get filtered voucher audit logs
     */
    public function index(ListVoucherAuditLogsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $logs = $this->voucherAuditLogRepository->getFilteredLogs($validated);

        return response()->json([
            'error' => false,
            'data' => [
                'current_page' => $logs->currentPage(),
                'data' => VoucherAuditLogResource::collection($logs->items()),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
            'message' => 'Voucher audit logs retrieved successfully.',
        ]);
    }
}
