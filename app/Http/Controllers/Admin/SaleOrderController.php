<?php

namespace App\Http\Controllers\Admin;

use App\Models\SaleOrder;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\SaleOrderResource;
use App\Repositories\SaleOrderRepository;
use App\Services\SaleOrder\ManavaultOrderCodeService;
use App\Http\Requests\SaleOrder\ListSaleOrderRequest;
use App\Actions\SaleOrder\DownloadManavaultCodesZipArchive;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $saleOrder->load('items.product');

        return response()->json([
            'error' => false,
            'data' => new SaleOrderResource($saleOrder),
            'message' => 'Sale order retrieved successfully.',
        ]);
    }

    public function codes(SaleOrder $saleOrder): JsonResponse
    {
        $codes = $this->manavaultOrderCodeService->listOrderCodes($saleOrder);

        return response()->json([
            'error' => false,
            'data' => $codes->values(),
            'message' => 'Sale order codes retrieved successfully.',
        ]);
    }

    public function downloadOrderCodes(
        SaleOrder $saleOrder,
    ): JsonResponse|StreamedResponse {
        $codes = $this->manavaultOrderCodeService->listOrderCodes($saleOrder);

        if ($codes->isEmpty()) {
            return response()->json([
                'error' => true,
                'message' => 'No code entries found for this sale order.',
            ], 404);
        }

        return $this->downloadManavaultCodesZipArchive->execute($saleOrder, $codes);
    }
}
