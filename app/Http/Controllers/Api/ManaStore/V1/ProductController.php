<?php

namespace App\Http\Controllers\Api\Manastore\V1;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Repositories\ProductRepository;
use App\Http\Requests\Api\Manastore\V1\ListProductRequest;
use App\Http\Resources\ManaStore\V1\ProductResource;

class ProductController extends Controller
{
    public function __construct(private ProductRepository $productRepository) {}

    public function index(ListProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $productIds = $validated['ids'] ?? [];
        $products = $this->productRepository->getAllProducts($productIds);
        $products->getCollection()->load('brand');

        $products->setCollection(
            $products->getCollection()->map(
                fn (Product $product) => ProductResource::make($product)->resolve($request)
            )
        );

        logger()->info('Products retrieved', ['ids' => $products->pluck('id')->toArray()]);

        return response()->json([
            'error' => false,
            'data' => $products,
            'message' => 'Products retrieved successfully.',
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load('brand');

        return response()->json([
            'error' => false,
            'data' => new ProductResource($product),
            'message' => 'Product retrieved successfully.',
        ]);
    }
}
