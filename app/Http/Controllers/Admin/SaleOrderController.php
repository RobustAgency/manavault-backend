<?php

namespace App\Http\Controllers\Admin;

use App\Models\SaleOrder;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\SaleOrderResource;
use App\Repositories\SaleOrderRepository;
use App\Http\Requests\SaleOrder\ListSaleOrderRequest;

class SaleOrderController extends Controller
{
    public function __construct(private SaleOrderRepository $saleOrderRepository) {}

    public function index(ListSaleOrderRequest $request): JsonResponse
    {
        $saleOrders = $this->saleOrderRepository->getFilteredSaleOrders($request->validated());

        return response()->json([
            'error' => false,
            'data' => $saleOrders,
            'message' => 'Sale orders retrieved successfully.',
        ]);
    }

    public function show(SaleOrder $saleOrder): JsonResponse
    {
        $saleOrder->load('items');

        return response()->json([
            'error' => false,
            'data' => new SaleOrderResource($saleOrder),
            'message' => 'Sale order retrieved successfully.',
        ]);
    }
}
