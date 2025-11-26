<?php

namespace App\Repositories;

use App\Models\Brand;
use Illuminate\Http\UploadedFile;
use App\Services\ImageUploadService;
use Illuminate\Pagination\LengthAwarePaginator;

class BrandRepository
{
    public function __construct(private ImageUploadService $imageUploadService) {}

    /**
     * Get paginated brands filtered by the provided criteria.
     *
     * @return LengthAwarePaginator<int, Brand>
     */
    public function getFilteredBrands(array $filters = []): LengthAwarePaginator
    {
        $query = Brand::query();

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('name', 'asc')->paginate($perPage);
    }

    /**
     * Create a new brand
     */
    public function createBrand(array $data): Brand
    {
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $data['image'] = $this->imageUploadService->upload($data['image'], 'uploads/brands');
        }

        return Brand::create($data);
    }

    /**
     * Update a brand
     */
    public function updateBrand(Brand $brand, array $data): Brand
    {
        if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
            $oldImage = $brand->image;
            $data['image'] = $this->imageUploadService->replace($data['image'], 'uploads/brands', $oldImage);
        }

        $brand->update($data);

        return $brand->fresh();
    }

    /**
     * Delete a brand
     */
    public function deleteBrand(Brand $brand): bool
    {
        if ($brand->image) {
            $this->imageUploadService->delete($brand->image);
        }

        return $brand->delete();
    }
}
