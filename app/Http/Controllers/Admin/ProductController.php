<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use App\Events\AssignDigitalProduct;
use App\Http\Controllers\Controller;
use App\Enums\Product\FulfillmentMode;
use App\Http\Resources\ProductResource;
use App\Repositories\ProductRepository;
use App\Events\DigitalProductPriorityChange;
use App\Http\Requests\Product\ListProductRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\AssignDigitalProductsRequest;
use App\Http\Requests\Product\UpdateDigitalProductsPriorityRequest;

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
        $product->load([
            'digitalProducts' => function ($query) use ($product) {
                if ($product->fulfillment_mode === FulfillmentMode::PRICE->value) {
                    $query->orderBy('cost_price', 'asc');
                } else {
                    $query->orderByPivot('priority', 'asc');
                }
            },
            'digitalProducts.supplier',
            'brand',
        ]);

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

        if (isset($validated['is_custom_priority']) && $validated['is_custom_priority'] === true) {
            $validated['fulfillment_mode'] = FulfillmentMode::MANUAL->value;
        } else {
            $validated['fulfillment_mode'] = FulfillmentMode::PRICE->value;
        }

        unset($validated['is_custom_priority']);

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

    public function assignDigitalProducts(Product $product, AssignDigitalProductsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $product->digitalProducts()->syncWithoutDetaching($validated['digital_product_ids']);

        event(new AssignDigitalProduct($product));

        return response()->json([
            'error' => false,
            'message' => 'Digital products assigned successfully.',
        ]);
    }

    public function removeDigitalProduct(Product $product, int $digitalProductId): JsonResponse
    {
        $product->digitalProducts()->detach($digitalProductId);
        event(new AssignDigitalProduct($product));

        return response()->json([
            'error' => false,
            'message' => 'Digital product removed successfully.',
        ]);
    }

    /**
     * Update the priority order of digital products for a product.
     */
    public function updateDigitalProductsPriority(Product $product, UpdateDigitalProductsPriorityRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->repository->updateDigitalProductsPriority($product, $validated['digital_products']);
        $product->load('digitalProducts');

        event(new DigitalProductPriorityChange($product));

        return response()->json([
            'error' => false,
            'data' => new ProductResource($product),
            'message' => 'Digital product priorities updated successfully.',
        ]);
    }
}
