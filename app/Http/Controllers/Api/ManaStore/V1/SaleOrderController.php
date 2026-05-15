<?php

namespace App\Http\Controllers\Api\Manastore\V1;

use App\Models\SaleOrder;
use Illuminate\Http\JsonResponse;
use App\Services\SaleOrderService;
use App\Http\Controllers\Controller;
use App\Repositories\SaleOrderRepository;
use App\Http\Requests\SaleOrder\StoreSaleOrderRequest;
use App\Http\Resources\ManaStore\V1\SaleOrderResource;
use App\Http\Resources\ManaStore\V1\VoucherCodesResource;
use App\Http\Resources\ManaStore\V1\SaleOrderDetailResource;

class SaleOrderController extends Controller
{
    public function __construct(
        private SaleOrderService $saleOrderService,
        private SaleOrderRepository $saleOrderRepository,
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
     * Get details of a sale order.
     */
    public function show(string $orderNumber): JsonResponse
    {
        try {
            $saleOrder = $this->saleOrderRepository->getSaleOrderByOrderNumber($orderNumber);

            if (! $saleOrder) {
                return response()->json([
                    'error' => true,
                    'message' => "Sale order with order number {$orderNumber} not found.",
                ], 404);
            }

            return response()->json([
                'error' => false,
                'message' => 'Sale order retrieved successfully.',
                'data' => new SaleOrderDetailResource($saleOrder),
            ], 200);
        } catch (\Exception $e) {
            logger()->error('Failed to retrieve sale order', ['error' => $e->getMessage()]);

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
            $saleOrder->load([
                'items.product',
                'items.digitalProducts.voucher',
            ]);
            $voucherCodes = VoucherCodesResource::format($saleOrder);

            return response()->json([
                'error' => false,
                'message' => 'Voucher codes retrieved successfully.',
                'data' => $voucherCodes,
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
