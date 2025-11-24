<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
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
            'data' => $logs,
            'message' => 'Voucher audit logs retrieved successfully.',
        ]);
    }
}
