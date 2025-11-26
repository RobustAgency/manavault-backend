<?php

namespace App\Repositories;

use App\Models\DigitalProduct;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class DigitalProductRepository
{
    /**
     * Get paginated digital products filtered by the provided criteria.
     *
     * @return LengthAwarePaginator<int, DigitalProduct>
     */
    public function getFilteredDigitalProducts(array $filters = []): LengthAwarePaginator
    {
        $query = DigitalProduct::query()->with('supplier');

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (isset($filters['brand'])) {
            $query->where('brand', 'like', '%'.$filters['brand'].'%');
        }

        if (isset($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        $per_page = $filters['per_page'] ?? 10;

        return $query->paginate($per_page);
    }

    /**
     * Create a single digital product.
     */
    public function createDigitalProduct(array $data): DigitalProduct
    {
        return DigitalProduct::create($data);
    }

    /**
     * Create multiple digital products in bulk.
     *
     * @param  array<int, array<string, mixed>>  $productsData
     * @return Collection<int, DigitalProduct>
     */
    public function createBulkDigitalProducts(array $productsData): Collection
    {
        $digitalProducts = collect();

        foreach ($productsData as $productData) {
            $digitalProducts->push($this->createDigitalProduct($productData));
        }

        return $digitalProducts;
    }

    /**
     * Update a digital product.
     */
    public function updateDigitalProduct(DigitalProduct $digitalProduct, array $data): DigitalProduct
    {
        $digitalProduct->update($data);

        return $digitalProduct->fresh();
    }

    /**
     * Delete a digital product.
     */
    public function deleteDigitalProduct(DigitalProduct $digitalProduct): bool
    {
        return $digitalProduct->delete();
    }

    /**
     * Get digital products by supplier.
     *
     * @return Collection<int, DigitalProduct>
     */
    public function getBySupplier(int $supplierId): Collection
    {
        return DigitalProduct::where('supplier_id', $supplierId)->get();
    }

    /**
     * Get active digital products.
     *
     * @return Collection<int, DigitalProduct>
     */
    public function getActiveProducts(): Collection
    {
        return DigitalProduct::where('status', 'active')->get();
    }

    public function createOrUpdate(array $attributes, array $values): DigitalProduct
    {
        return DigitalProduct::updateOrCreate($attributes, $values);
    }
}
