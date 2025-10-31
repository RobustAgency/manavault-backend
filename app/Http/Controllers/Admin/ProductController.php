<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ListProductRequest;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(private ProductRepository $repository) {}

    public function index(ListProductRequest $request): JsonResponse
    {
        $products = $this->repository->getFilteredProducts($request->validated());
        return response()->json([
            'error' => false,
            'data' => $products,
            'message' => 'Products retrieved successfully.',
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'error' => false,
            'data' => new ProductResource($product),
            'message' => 'Product retrieved successfully.',
        ]);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $product = $this->repository->createProduct($validated);
        return response()->json([
            'error' => false,
            'data' => new ProductResource($product),
            'message' => 'Product created successfully.',
        ], 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();
        $product = $this->repository->updateProduct($product, $validated);
        return response()->json([
            'error' => false,
            'data' => new ProductResource($product),
            'message' => 'Product updated successfully.',
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->repository->deleteProduct($product);
        return response()->json([
            'error' => false,
            'message' => 'Product deleted successfully.',
        ]);
    }
}
