<?php

namespace App\Http\Controllers\Admin;

use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Repositories\PurchaseOrderRepository;
use App\Http\Requests\PurchaseOrder\ListPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;

class PurchaseOrderController extends Controller
{
    public function __construct(private PurchaseOrderRepository $repository) {}

    /**
     * Display a listing of purchase orders.
     */
    public function index(ListPurchaseOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $purchaseOrders = $this->repository->getPaginatedPurchaseOrders($validated);

        return response()->json([
            'error' => false,
            'data' => $purchaseOrders,
            'message' => 'Purchase orders retrieved successfully.',
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        try {
            $purchaseOrder = $this->repository->createPurchaseOrder($validated);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to create purchase order: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'error' => false,
            'data' => new PurchaseOrderResource($purchaseOrder),
            'message' => 'Purchase order created successfully.',
        ], 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load(['product', 'supplier', 'vouchers']);

        return response()->json([
            'error' => false,
            'data' => new PurchaseOrderResource($purchaseOrder),
            'message' => 'Purchase order retrieved successfully.',
        ]);
    }
}
