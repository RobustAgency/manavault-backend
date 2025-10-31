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
        $data = [
            'filePath' => $validated['file']->getRealPath(),
            'purchaseOrderID' => $validated['purchase_order_id'],
        ];

        $vouchers = $this->voucherRepository->importVouchers($data);

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
