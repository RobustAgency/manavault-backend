<?php

namespace App\Http\Controllers\Admin;

use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Repositories\BrandRepository;
use App\Http\Requests\Brand\ListBrandsRequest;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;

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

    /**
     * Display the specified brand.
     */
    public function show(Brand $brand): JsonResponse
    {
        return response()->json([
            'error' => false,
            'data' => new BrandResource($brand),
            'message' => 'Brand retrieved successfully.',
        ]);
    }

    /**
     * Update the specified brand.
     */
    public function update(UpdateBrandRequest $request, Brand $brand): JsonResponse
    {
        $validated = $request->validated();
        $brand = $this->brandRepository->updateBrand($brand, $validated);

        return response()->json([
            'error' => false,
            'data' => new BrandResource($brand),
            'message' => 'Brand updated successfully.',
        ]);
    }

    /**
     * Remove the specified brand.
     */
    public function destroy(Brand $brand): JsonResponse
    {
        $this->brandRepository->deleteBrand($brand);

        return response()->json([
            'error' => false,
            'data' => null,
            'message' => 'Brand deleted successfully.',
        ]);
    }
}
