<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\ListPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Repositories\PurchaseOrderRepository;
use Illuminate\Http\JsonResponse;
use Ramsey\Uuid\Uuid;

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
        $validated['order_number'] = Uuid::uuid4()->toString();
        $purchaseOrder = $this->repository->createPurchaseOrder($validated);

        return response()->json([
            'error' => false,
            'data' => new PurchaseOrderResource($purchaseOrder),
            'message' => 'Purchase order created successfully.',
        ], 201);
    }
}
