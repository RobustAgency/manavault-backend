<?php

namespace App\Http\Controllers\Api\ManaStore\V1;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
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
}
