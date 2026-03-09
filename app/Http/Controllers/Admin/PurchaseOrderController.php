<?php

namespace App\Http\Controllers\Admin;

use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Repositories\PurchaseOrderRepository;
use App\Services\Ezcards\EzcardsVoucherCodeService;
use App\Http\Requests\PurchaseOrder\ListPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private PurchaseOrderRepository $repository,
        private EzcardsVoucherCodeService $ezcardsVoucherCodeService
    ) {}

    /**
     * Display a listing of purchase orders.
     */
    public function index(ListPurchaseOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $purchaseOrders = $this->repository->getFilteredPurchaseOrders($validated);

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
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
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
        $purchaseOrder->load([
            'purchaseOrderSuppliers.supplier',
            'items',
            'vouchers',
        ]);

        return response()->json([
            'error' => false,
            'data' => new PurchaseOrderResource($purchaseOrder),
            'message' => 'Purchase order retrieved successfully.',
        ]);
    }

    /**
     * Process a purchase order by ID to fetch and store voucher codes.
     */
    public function purchaseOrderVouchers(PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $this->ezcardsVoucherCodeService->processPurchaseOrderById($purchaseOrder);

            return response()->json([
                'error' => false,
                'data' => null,
                'message' => 'Purchase order vouchers processed successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to process vouchers: '.$e->getMessage(),
            ], 500);
        }
    }
}
