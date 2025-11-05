<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Voucher\ImportVoucherRequest;
use App\Repositories\VoucherRepository;
use Illuminate\Http\JsonResponse;

class VoucherController extends Controller
{
    public function __construct(private VoucherRepository $voucherRepository) {}

    public function importVouchers(ImportVoucherRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $vouchers = $this->voucherRepository->importVouchers($validated);

        if (! $vouchers) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to import vouchers.',
            ]);
        }
        return response()->json([
            'error' => false,
            'message' => 'Vouchers imported successfully.',
            'data' => $vouchers,
        ]);
    }
}
