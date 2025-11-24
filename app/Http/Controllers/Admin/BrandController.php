<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Repositories\BrandRepository;
use App\Http\Requests\Brand\ListBrandsRequest;
use App\Http\Requests\Brand\StoreBrandRequest;

class BrandController extends Controller
{
    public function __construct(private BrandRepository $brandRepository) {}

    /**
     * Display a listing of brands.
     */
    public function index(ListBrandsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $brands = $this->brandRepository->getFilteredBrands($validated);

        return response()->json([
            'error' => false,
            'data' => $brands,
            'message' => 'Brands retrieved successfully.',
        ]);
    }

    /**
     * Store a newly created brand.
     */
    public function store(StoreBrandRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $brand = $this->brandRepository->createBrand($validated);

        return response()->json([
            'error' => false,
            'data' => new BrandResource($brand),
            'message' => 'Brand created successfully.',
        ], 201);
    }
}
