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
        $query = Product::with('brand');

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        $per_page = $filters['per_page'] ?? 10;

        return $query->orderBy('created_at', 'desc')->paginate($per_page);
    }

    public function createProduct(array $data): Product
    {
        $image = $data['image'] ?? null;
        if ($image instanceof UploadedFile) {
            $data['image'] = $this->uploadProductImage($image);
        }

        return Product::create($data);
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $image = $data['image'] ?? null;
        if ($image instanceof UploadedFile) {
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
