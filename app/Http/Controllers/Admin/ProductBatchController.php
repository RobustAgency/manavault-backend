<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Repositories\ProductRepository;
use App\Http\Requests\Product\StoreProductBatchRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;

class ProductBatchController extends Controller
{
    public function __construct(private ProductRepository $repository) {}

    public function store(StoreProductBatchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $product = $this->repository->createProductBatch($validated);
        return response()->json([
            'error' => false,
            'data' => new ProductResource($product),
            'message' => 'Product batch created successfully.',
        ], 201);
    }
}
