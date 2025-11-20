<?php

namespace App\Http\Controllers\Admin;

use App\Models\DigitalProduct;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\DigitalProductResource;
use App\Repositories\DigitalProductRepository;
use App\Http\Requests\DigitalProduct\StoreDigitalProductRequest;
use App\Http\Requests\DigitalProduct\UpdateDigitalProductRequest;

class DigitalProductController extends Controller
{
    public function __construct(private DigitalProductRepository $repository) {}

    /**
     * Store newly created digital products in storage.
     */
    public function store(StoreDigitalProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $digitalProducts = $this->repository->createBulkDigitalProducts($validated['products']);

        return response()->json([
            'error' => false,
            'data' => $digitalProducts,
            'message' => 'Digital products created successfully.',
        ], 201);
    }

    /**
     * Display the specified digital product.
     */
    public function show(DigitalProduct $digitalProduct): JsonResponse
    {
        $digitalProduct->load('supplier');

        return response()->json([
            'error' => false,
            'data' => new DigitalProductResource($digitalProduct),
            'message' => 'Digital product retrieved successfully.',
        ]);
    }

    /**
     * Update the specified digital product in storage.
     */
    public function update(UpdateDigitalProductRequest $request, DigitalProduct $digitalProduct): JsonResponse
    {
        $validated = $request->validated();
        $digitalProduct = $this->repository->updateDigitalProduct($digitalProduct, $validated);

        return response()->json([
            'error' => false,
            'data' => new DigitalProductResource($digitalProduct),
            'message' => 'Digital product updated successfully.',
        ]);
    }

    /**
     * Remove the specified digital product from storage.
     */
    public function destroy(DigitalProduct $digitalProduct): JsonResponse
    {
        $this->repository->deleteDigitalProduct($digitalProduct);

        return response()->json([
            'error' => false,
            'message' => 'Digital product deleted successfully.',
        ]);
    }
}
