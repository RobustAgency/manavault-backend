<?php

namespace App\Actions\SaleOrderItem;

use App\Models\SaleOrderItem;

class BackfillDigitalProductIdAction
{
    public function execute(): void
    {
        $items = SaleOrderItem::whereNull('digital_product_id')
            ->with(['product', 'digitalProducts'])
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $digitalProductId = $this->resolveDigitalProductId($item);

            if ($digitalProductId === null) {
                logger()->info('BackfillDigitalProductIdAction: could not resolve digital product, skipping', [
                    'sale_order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                ]);

                $skipped++;

                continue;
            }

            $item->update(['digital_product_id' => $digitalProductId]);

            $updated++;
        }

        logger()->info('BackfillDigitalProductIdAction: finished', [
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Resolve the digital product id for an item, preferring the supplier
     * that was actually deducted historically, then falling back to the
     * same selection logic used at order creation time.
     */
    private function resolveDigitalProductId(SaleOrderItem $item): ?int
    {
        // 1. Prefer the digital product actually allocated for this item.
        //    Each row is a single allocated voucher, so pick the most
        //    frequently allocated product, ignoring rows whose product was deleted.
        $allocatedId = $item->digitalProducts
            ->whereNotNull('digital_product_id')
            ->groupBy('digital_product_id')
            ->map->count()
            ->sortDesc()
            ->keys()
            ->first();

        if ($allocatedId !== null) {
            return (int) $allocatedId;
        }

        // 2. Fall back to re-resolving the supplier digital product from the product.
        return $item->product?->digitalProduct()?->id;
    }
}
