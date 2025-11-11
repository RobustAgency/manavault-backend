<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\ListSupplierRequest;
use App\Http\Requests\Suppliers\StoreSupplierRequest;
use App\Http\Requests\Suppliers\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Repositories\SupplierRepository;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    public function __construct(private SupplierRepository $repository) {}

    public function index(ListSupplierRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $suppliers = $this->repository->getFilteredSuppliers($validated);
        return response()->json([
            'error' => false,
            'data' => $suppliers,
            'message' => 'Suppliers retrieved successfully.',
        ]);
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $supplier = $this->repository->createSupplier($validated);
        return response()->json([
            'error' => false,
            'data' => $supplier,
            'message' => 'Supplier created successfully.',
        ], 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'error' => false,
            'data' => new SupplierResource($supplier),
            'message' => 'Supplier retrieved successfully.',
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $this->repository->updateSupplier($supplier, $request->validated());
        return response()->json([
            'error' => false,
            'data' => null,
            'message' => 'Supplier updated successfully.',
        ]);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        $this->repository->deleteSupplier($supplier);
        return response()->json([
            'error' => false,
            'data' => null,
            'message' => 'Supplier deleted successfully.',
        ]);
    }
}
