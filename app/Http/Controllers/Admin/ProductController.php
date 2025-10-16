<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Repositories\ProductRepository;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(private ProductRepository $repository) {}

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
}
