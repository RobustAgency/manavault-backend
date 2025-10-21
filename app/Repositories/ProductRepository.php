<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository
{
    /**
     * Get paginated products filtered by the provided criteria.
     * @param array $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function getFilteredProducts(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query();

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        $per_page = $filters['per_page'] ?? 10;

        return $query->paginate($per_page);
    }

    public function createProduct(array $data): Product
    {
        return Product::create($data);
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $product->update($data);
        return $product;
    }

    public function deleteProduct(Product $product): bool
    {
        return $product->delete();
    }
}
