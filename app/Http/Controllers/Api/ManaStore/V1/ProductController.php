<?php

namespace App\Http\Controllers\Api\Manastore\V1;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Repositories\ProductRepository;
use App\Http\Requests\Api\Manastore\V1\ListProductRequest;

class ProductController extends Controller
{
    public function __construct(private ProductRepository $productRepository) {}

    public function index(ListProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $productIds = $validated['ids'] ?? [];
        $products = $this->productRepository->getAllProducts($productIds);

        logger()->info('Products retrieved', ['ids' => $products->pluck('id')->toArray()]);

        return response()->json([
            'error' => false,
            'data' => $products,
            'message' => 'Products retrieved successfully.',
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['brand', 'digitalProducts.supplier']);

        return response()->json([
            'error' => false,
            'data' => new ProductResource($product),
            'message' => 'Product retrieved successfully.',
        ]);
    }
}
