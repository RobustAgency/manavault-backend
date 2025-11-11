<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListVouchersRequest;
use App\Http\Requests\Voucher\ImportVoucherRequest;
use App\Repositories\VoucherRepository;
use Illuminate\Http\JsonResponse;

class VoucherController extends Controller
{
    public function __construct(private VoucherRepository $voucherRepository) {}

    public function index(ListVouchersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $vouchers = $this->voucherRepository->getFilteredVouchers($validated);
        return response()->json([
            'error' => false,
            'data' => $vouchers,
            'message' => 'Vouchers retrieved successfully.',
        ]);
    }

    public function importVouchers(ImportVoucherRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->voucherRepository->importVouchers($validated);
            return response()->json([
                'error' => false,
                'message' => 'Vouchers imported successfully.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to import vouchers: ' . $e->getMessage(),
            ]);
        }
    }
}
