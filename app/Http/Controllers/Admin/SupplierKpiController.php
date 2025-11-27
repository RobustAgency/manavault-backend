<?php

namespace App\Http\Controllers\Admin;

use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Services\Supplier\SupplierKpiService;

class SupplierKpiController extends Controller
{
    public function __construct(private SupplierKpiService $supplierKpiService) {}

    public function show(Supplier $supplier): JsonResponse
    {
        $kpis = $this->supplierKpiService->getSupplierKpis($supplier);

        return response()->json([
            'error' => false,
            'data' => $kpis,
            'message' => 'Supplier KPIs retrieved successfully.',
        ]);
    }
}
