<?php

namespace App\Http\Controllers\Admin;

use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\VoucherResource;
use App\Repositories\VoucherRepository;
use App\Http\Requests\ListVouchersRequest;
use App\Http\Requests\Voucher\StoreVoucherRequest;

class VoucherController extends Controller
{
    public function __construct(private VoucherRepository $voucherRepository) {}

    public function index(ListVouchersRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $vouchers = $this->voucherRepository->getFilteredVouchers($validated);

        return response()->json([
            'error' => false,
            'data' => [
                'current_page' => $vouchers->currentPage(),
                'data' => VoucherResource::collection($vouchers->items()),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
                'last_page' => $vouchers->lastPage(),
            ],
            'message' => 'Vouchers retrieved successfully.',
        ]);
    }

    public function store(StoreVoucherRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->voucherRepository->storeVouchers($validated);

            return response()->json([
                'error' => false,
                'message' => 'Vouchers imported successfully.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to import vouchers: '.$e->getMessage(),
            ]);
        }
    }

    public function show(Voucher $voucher): JsonResponse
    {
        $voucherCode = $this->voucherRepository->decryptVoucherCode($voucher);

        return response()->json([
            'error' => false,
            'data' => [
                'id' => $voucher->id,
                'code' => $voucherCode,
            ],
            'message' => 'Voucher retrieved successfully.',
        ]);
    }
}
