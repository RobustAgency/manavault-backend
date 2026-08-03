<?php

namespace App\Http\Controllers\Admin;

use App\Models\SaleOrder;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\SaleOrderResource;
use App\Repositories\SaleOrderRepository;
use App\Http\Requests\SaleOrder\ListSaleOrderRequest;
use App\Services\SaleOrder\ManavaultOrderCodeService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Actions\SaleOrder\DownloadManavaultCodesZipArchive;

class SaleOrderController extends Controller
{
    public function __construct(
        private SaleOrderRepository $saleOrderRepository,
        private ManavaultOrderCodeService $manavaultOrderCodeService,
        private DownloadManavaultCodesZipArchive $downloadManavaultCodesZipArchive,
    ) {}

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
        $saleOrder->load('items.product', 'customer');

        return response()->json([
            'error' => false,
            'data' => new SaleOrderResource($saleOrder),
            'message' => 'Sale order retrieved successfully.',
        ]);
    }

    public function codes(SaleOrder $saleOrder): JsonResponse
    {
        return response()->json([
            'error' => true,
            'message' => 'Something went wrong.',
        ], 500);
    }

    public function downloadOrderCodes(
        SaleOrder $saleOrder,
    ): JsonResponse|StreamedResponse {
        return response()->json([
            'error' => true,
            'message' => 'Something went wrong.',
        ], 500);
    }
}
