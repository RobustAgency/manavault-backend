<?php

namespace App\Http\Controllers\Api\Manastore\V1;

use Illuminate\Http\JsonResponse;
use App\Services\SaleOrderService;
use App\Http\Controllers\Controller;
use App\Http\Resources\SaleOrderResource;
use App\Http\Requests\SaleOrder\StoreSaleOrderRequest;

class SaleOrderController extends Controller
{
    public function __construct(private SaleOrderService $saleOrderService) {}

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
}
