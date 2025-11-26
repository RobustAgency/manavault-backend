<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use App\Services\ImageUploadService;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository
{
    public function __construct(private ImageUploadService $imageUploadService) {}

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
            $data['image'] = $this->imageUploadService->upload($image, 'uploads/products');
        }

        return Product::create($data);
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $image = $data['image'] ?? null;
        if ($image instanceof UploadedFile) {
            $oldImage = $product->image;
            $data['image'] = $this->imageUploadService->replace($image, 'uploads/products', $oldImage);
        }
        $product->update($data);

        return $product;
    }

    public function deleteProduct(Product $product): bool
    {
        if ($product->image) {
            $this->imageUploadService->delete($product->image);
        }

        return $product->delete();
    }
}
