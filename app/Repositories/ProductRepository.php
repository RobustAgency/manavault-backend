<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository
{
    /**
     * Get paginated products filtered by the provided criteria.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function getFilteredProducts(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query();

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['brand'])) {
            $query->where('brand', 'like', '%'.$filters['brand'].'%');
        }

        $per_page = $filters['per_page'] ?? 10;

        return $query->paginate($per_page);
    }

    public function createProduct(array $data): Product
    {
        $image = $data['image'] ?? null;
        if ($image) {
            $data['image'] = $this->uploadProductImage($image);
        }

        return Product::create($data);
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $image = $data['image'] ?? null;
        if ($image) {
            $data['image'] = $this->uploadProductImage($image);
        }
        $product->update($data);

        return $product;
    }

    public function deleteProduct(Product $product): bool
    {
        return $product->delete();
    }

    public function uploadProductImage(UploadedFile $image): string|false
    {
        return $image->store('uploads/products', 'public');
    }
}
