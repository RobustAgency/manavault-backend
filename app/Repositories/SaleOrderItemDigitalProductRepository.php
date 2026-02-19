<?php

namespace App\Repositories;

use App\Models\SaleOrderItemDigitalProduct;

class SaleOrderItemDigitalProductRepository
{
    /**
     * Allocate a voucher to a sale order item digital product.
     */
    public function allocateVoucher(
        int $saleOrderItemId,
        int $digitalProductId,
        int $voucherId
    ): SaleOrderItemDigitalProduct {
        return SaleOrderItemDigitalProduct::create([
            'sale_order_item_id' => $saleOrderItemId,
            'digital_product_id' => $digitalProductId,
            'voucher_id' => $voucherId,
        ]);
    }

    /**
     * Get all allocations for a sale order item.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SaleOrderItemDigitalProduct>
     */
    public function getAllocationsBySaleOrderItem(int $saleOrderItemId)
    {
        return SaleOrderItemDigitalProduct::where('sale_order_item_id', $saleOrderItemId)
            ->with('voucher', 'digitalProduct')
            ->get();
    }

    /**
     * Get all allocations for a specific digital product in a sale order item.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SaleOrderItemDigitalProduct>
     */
    public function getAllocationsByDigitalProduct(int $saleOrderItemId, int $digitalProductId)
    {
        return SaleOrderItemDigitalProduct::where('sale_order_item_id', $saleOrderItemId)
            ->where('digital_product_id', $digitalProductId)
            ->with('voucher')
            ->get();
    }

    /**
     * Get allocation count for a digital product in a sale order item.
     */
    public function getAllocationCount(int $saleOrderItemId, int $digitalProductId): int
    {
        return SaleOrderItemDigitalProduct::where('sale_order_item_id', $saleOrderItemId)
            ->where('digital_product_id', $digitalProductId)
            ->count();
    }

    /**
     * Delete all allocations for a sale order item.
     *
     * @return int Number of records deleted
     */
    public function deleteAllocationsBySaleOrderItem(int $saleOrderItemId): int
    {
        return SaleOrderItemDigitalProduct::where('sale_order_item_id', $saleOrderItemId)->delete();
    }
}
