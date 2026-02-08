<?php

namespace App\Http\Controllers\Api\Manastore\V1;

use App\Models\SaleOrder;
use Illuminate\Http\JsonResponse;
use App\Services\SaleOrderService;
use App\Http\Controllers\Controller;
use App\Http\Resources\VoucherResource;
use App\Repositories\VoucherRepository;
use App\Http\Resources\SaleOrderResource;
use App\Http\Requests\SaleOrder\StoreSaleOrderRequest;

class SaleOrderController extends Controller
{
    public function __construct(
        private SaleOrderService $saleOrderService,
        private VoucherRepository $voucherRepository
    ) {}

    /**
     * Create a new sale order.
     */
    public function store(StoreSaleOrderRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $saleOrder = $this->saleOrderService->createOrder($validated);

            return response()->json([
                'error' => false,
                'message' => 'Sale order created successfully.',
                'data' => new SaleOrderResource($saleOrder),
            ], 201);
        } catch (\Exception $e) {
            logger()->error('Failed to create sale order', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get all vouchers allocated to a sale order.
     */
    public function getVouchers(SaleOrder $saleOrder): JsonResponse
    {
        try {
            $vouchers = $this->voucherRepository->getVouchersForSaleOrder($saleOrder->id);

            return response()->json([
                'error' => false,
                'message' => 'Vouchers retrieved successfully.',
                'data' => VoucherResource::collection($vouchers),
            ], 200);
        } catch (\Exception $e) {
            logger()->error('Failed to retrieve vouchers', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
