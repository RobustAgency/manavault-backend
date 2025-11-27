<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Repositories\SupplierKpiRepository;
use App\Http\Requests\Suppliers\ListSupplierKpiRequest;

class SupplierKpiController extends Controller
{
    public function __construct(private SupplierKpiRepository $supplierKpiRepository) {}

    public function index(ListSupplierKpiRequest $request): JsonResponse
    {
        $supplierKPIs = $this->supplierKpiRepository->getFilteredSuppliersKpis($request->validated());

        return response()->json([
            'error' => false,
            'data' => $supplierKPIs,
            'message' => 'Supplier KPIs retrieved successfully.',
        ]);
    }
}
