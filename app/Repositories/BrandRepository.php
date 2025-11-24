<?php

namespace App\Repositories;

use App\Models\Brand;
use Illuminate\Pagination\LengthAwarePaginator;

class BrandRepository
{
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
        return Brand::create($data);
    }

    /**
     * Update a brand
     */
    public function updateBrand(Brand $brand, array $data): Brand
    {
        $brand->update($data);

        return $brand->fresh();
    }

    /**
     * Delete a brand
     */
    public function deleteBrand(Brand $brand): bool
    {
        return $brand->delete();
    }
}
