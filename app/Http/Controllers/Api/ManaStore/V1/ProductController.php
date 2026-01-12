<?php

namespace App\Http\Controllers\Api\Manastore\V1;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Repositories\ProductRepository;

class ProductController extends Controller
{
    public function __construct(private ProductRepository $productRepository) {}

    public function index(): JsonResponse
    {
        $products = $this->productRepository->getAllProducts();

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
}
