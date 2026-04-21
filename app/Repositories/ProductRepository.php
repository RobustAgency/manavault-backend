<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use App\Services\ImageUploadService;
use App\Enums\PriceRuleCondition\Operator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository
{
    public function __construct(
        private ImageUploadService $imageUploadService,
        private BrandRepository $brandRepository
    ) {}

    /**
     * Get paginated products filtered by the provided criteria.
     *
     * @return LengthAwarePaginator<int, Product>
     */
    public function getFilteredProducts(array $filters = []): LengthAwarePaginator
    {
        $query = Product::with(['digitalProducts.supplier', 'brand']);

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (isset($filters['status'])) {
            $status = $filters['status'];
            $query->where('status', $status);

            // When filtering by 'active' status, ensure product has at least one digital product assigned
            if ($status === 'active') {
                $query->whereHas('digitalProducts');
            }
        }

        if (isset($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        if (isset($filters['currency'])) {
            $query->where('currency', $filters['currency']);
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

    /**
     * Get products that match dynamic conditions (used by pricing rules).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function getProductsByConditions(array $conditions, string $matchType = 'all'): Collection
    {
        $query = Product::query();

        $applyCondition = function ($q, array $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = $condition['value'];

            if ($field === 'brand_name') {
                $brand = $this->brandRepository->getBrandByName($value);
                if ($brand) {
                    $field = 'brand_id';
                    $value = (string) $brand->id;
                } else {
                    return;
                }
            }

            match ($operator) {
                Operator::EQUAL->value => $q->where($field, $value),
                Operator::NOT_EQUAL->value => $q->where($field, '!=', $value),
                Operator::GREATER_THAN->value => $q->where($field, '>', $value),
                Operator::LESS_THAN->value => $q->where($field, '<', $value),
                Operator::GREATER_THAN_OR_EQUAL->value => $q->where($field, '>=', $value),
                Operator::LESS_THAN_OR_EQUAL->value => $q->where($field, '<=', $value),
                Operator::CONTAINS->value => $q->where($field, 'LIKE', "%{$value}%"),
                default => null,
            };
        };

        if ($matchType === 'any') {
            $query->where(function ($q) use ($conditions, $applyCondition) {
                foreach ($conditions as $condition) {
                    $q->orWhere(fn ($sub) => $applyCondition($sub, $condition));
                }
            });
        } else {
            foreach ($conditions as $condition) {
                $applyCondition($query, $condition);
            }
        }

        return $query->get();
    }

    /**
     * Update the priority order of digital products for a given product.
     */
    public function updateDigitalProductsPriority(Product $product, array $digitalProducts): void
    {
        $updates = [];
        foreach ($digitalProducts as $item) {
            $updates[$item['digital_product_id']] = ['priority' => $item['priority_order']];
        }

        $product->digitalProducts()->syncWithoutDetaching($updates);
    }

    /**
     * Get all products.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, Product>
     */
    public function getAllProducts(array $productIds = []): LengthAwarePaginator
    {
        return Product::query()
            ->with(['digitalProducts.supplier', 'brand'])
            ->when(
                ! empty($productIds),
                fn ($q) => $q->whereIn('id', $productIds)
            )
            ->paginate(100);
    }

    public function getProductById(int $id): ?Product
    {
        return Product::find($id);
    }

    public function getProductIdsByDigitalProductIds(array $digitalProductIds): array
    {
        if (empty($digitalProductIds)) {
            return [];
        }

        return Product::query()
            ->whereHas('digitalProducts', fn ($q) => $q->whereIn('digital_products.id', $digitalProductIds)
            )
            ->pluck('id')
            ->all();
    }

    public function getProductIdsByDigitalProductId(int $digitalProductId): array
    {
        return Product::query()
            ->whereHas('digitalProducts', fn ($q) => $q->where('digital_products.id', $digitalProductId))
            ->pluck('id')
            ->all();
    }

    /**
     * Get products by brand ID.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    public function getProductsByBrandId(int $brandId): Collection
    {
        return Product::where('brand_id', $brandId)->get();
    }

    /**
     * Get all unique regions from the regions column across all products.
     *
     * @return array<int, string>
     */
    public function getUniqueRegions(): array
    {
        return Product::whereNotNull('regions')
            ->pluck('regions')
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
