<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\ProductBatch;

class ProductRepository
{
    public function createProductBatch(array $data): Product
    {
        $product = Product::firstOrCreate(
            ['name' => $data['name']],
            [
                'sku' => $data['sku'] ?? uniqid('SKU-'),
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
            ]
        );

        ProductBatch::create([
            'product_id' => $product->id,
            'supplier_id' => $data['supplier_id'],
            'purchase_price' => $data['purchase_price'],
            'quantity' => $data['quantity'],
        ]);

        return $product;
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $product->update($data);
        return $product;
    }
}
