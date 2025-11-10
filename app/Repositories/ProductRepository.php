<?php

namespace App\Repositories;

use App\Actions\Ezcards\GetProducts as EzCardsGetProducts;
use App\Actions\Gift2Games\GetProducts as Gift2GamesGetProducts;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository
{
    public function __construct(
        private EzCardsGetProducts $ezGetProducts,
        private Gift2GamesGetProducts $gift2GamesGetProducts
    ) {}
    /**
     * Get paginated products filtered by the provided criteria.
     * @param array $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function getFilteredProducts(array $filters = []): LengthAwarePaginator
    {
        $query = Product::with('supplier');

        if (isset($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['supplier_id'])) {
            $query->where('supplier_id', $filters['supplier_id']);
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

    /**
     * Fetch products from third-party services based on slug.
     *
     * @param string $slug
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function listThirdPartyProducts(string $slug, int $limit, int $offset): array
    {
        switch ($slug) {
            case 'ez_cards':
                return $this->ezGetProducts->execute($limit, $offset);
            case 'gift2games':
                return $this->gift2GamesGetProducts->execute($offset, $limit);
            default:
                return [];
        }
    }
}
