<?php

namespace App\Http\Controllers\Api\Manastore\V1;

use App\Models\SaleOrder;
use Illuminate\Http\JsonResponse;
use App\Services\SaleOrderService;
use App\Http\Controllers\Controller;
use App\Http\Resources\SaleOrderResource;
use App\Repositories\SaleOrderRepository;
use App\Http\Resources\ManaStore\V1\VoucherResource;
use App\Http\Requests\SaleOrder\StoreSaleOrderRequest;

class SaleOrderController extends Controller
{
    public function __construct(
        private SaleOrderService $saleOrderService,
        private SaleOrderRepository $saleOrderRepository
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
    public function getVoucherCodes(SaleOrder $saleOrder): JsonResponse
    {
        try {
            $voucherCodes = $this->saleOrderRepository->getSaleOrderVoucherCode($saleOrder);

            return response()->json([
                'error' => false,
                'message' => 'Voucher codes retrieved successfully.',
                'data' => VoucherResource::collection($voucherCodes),
            ], 200);
        } catch (\Exception $e) {
            logger()->error('Failed to retrieve voucher codes', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
