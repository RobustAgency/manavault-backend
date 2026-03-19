<?php

namespace App\Repositories;

use App\Models\DigitalProduct;
use Illuminate\Support\Collection;
use App\Services\ImageUploadService;
use App\Enums\PriceRuleCondition\Operator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class DigitalProductRepository
{
    public function __construct(
        private ImageUploadService $imageUploadService,
    ) {}

    /**
     * Get paginated digital products filtered by the provided criteria.
     *
     * @return LengthAwarePaginator<int, DigitalProduct>
     */
    public function getFilteredDigitalProducts(array $filters = []): LengthAwarePaginator
    {
        $query = DigitalProduct::query()->with('supplier');

        $query->where('in_stock', true)->where('is_active', true);

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (isset($filters['brand'])) {
            $query->where('brand', 'like', '%'.$filters['brand'].'%');
        }

        if (isset($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
        }

        if (isset($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        $per_page = $filters['per_page'] ?? 10;

        $query->orderBy('created_at', 'desc');

        return $query->paginate($per_page);
    }

    /**
     * Create a single digital product.
     */
    public function createDigitalProduct(array $data): DigitalProduct
    {
        $data['last_synced_at'] = now();

        if (! empty($data['image'])) {
            $uploadedImage = $this->imageUploadService->upload($data['image'], 'uploads/digital-products');
            if (is_string($uploadedImage)) {
                $appUrl = config('app.url');
                $data['image_url'] = rtrim($appUrl, '/').'/storage/'.ltrim($uploadedImage, '/');
            }

            unset($data['image']);

        }

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
        $oldImage = $digitalProduct->image_url;
        // TODO: Implement DELETE /api/images/{id} to remove file from storage and set image_url to null in db
        // Handle image deletion when image key is explicitly set to null
        if (array_key_exists('image', $data) && $data['image'] === null) {
            // Delete the old image from storage if it exists
            if (! empty($oldImage)) {
                $oldImage = str_replace(config('app.url').'/storage/', '', $oldImage);
                $this->imageUploadService->delete($oldImage);
            }

            $data['image_url'] = null;
            unset($data['image']);
        } elseif (! empty($data['image'])) {
            // Handle image replacement with a new file
            if (! empty($oldImage)) {
                $oldImage = str_replace(config('app.url').'/storage/', '', $oldImage);
            }

            $replacedImage = $this->imageUploadService->replace($data['image'], 'uploads/digital-products', $oldImage);

            if (is_string($replacedImage)) {
                $appUrl = config('app.url');
                $data['image_url'] = rtrim($appUrl, '/').'/storage/'.ltrim($replacedImage, '/');
            }

            unset($data['image']);
        }

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

    /**
     * Deactivate all digital products for a supplier whose SKU is not in the provided list.
     * Called after a sync to mark products removed by the supplier as inactive.
     *
     * @param  array<int, string>  $activeSKUs
     */
    public function deactivateStaleBySupplierId(int $supplierId, array $activeSKUs): int
    {
        return DigitalProduct::where('supplier_id', $supplierId)
            ->whereNotIn('sku', $activeSKUs)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * Get digital products that match dynamic conditions (used by pricing rules).
     *
     * Supports direct digital_products columns (supplier_id, brand, cost_price, face_value,
     * selling_price, currency, region) and cross-table fields (brand_id, brand_name)
     * resolved via the product_supplier pivot → products table.
     *
     * @return EloquentCollection<int, DigitalProduct>
     */
    public function getDigitalProductsByConditions(array $conditions, string $matchType = 'all'): EloquentCollection
    {
        $query = DigitalProduct::query();

        $applyCondition = function ($q, array $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = $condition['value'];

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
}
