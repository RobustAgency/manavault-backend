<?php

namespace App\Services\SaleOrder;

use App\Models\SaleOrder;
use Illuminate\Support\Collection;
use App\Services\Voucher\VoucherCipherService;

class ManavaultOrderCodeService
{
    public function __construct(private VoucherCipherService $voucherCipherService) {}

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listOrderCodes(SaleOrder $saleOrder): Collection
    {
        $saleOrder->loadMissing([
            'items.product',
            'items.digitalProducts.digitalProduct',
            'items.digitalProducts.voucher',
        ]);

        return $saleOrder->items
            ->flatMap(function ($item) use ($saleOrder) {
                return $item->digitalProducts
                    ->filter(fn ($entry) => $entry->voucher !== null)
                    ->map(function ($entry) use ($item, $saleOrder) {
                        $voucher = $entry->voucher;
                        $digitalProduct = $entry->digitalProduct;

                        return [
                            'id' => $entry->id,
                            'sale_order_id' => $saleOrder->id,
                            'sale_order_item_id' => $item->id,
                            'voucher_id' => $voucher?->id,
                            'order_number' => $saleOrder->order_number,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product?->name,
                            'digital_product_id' => $entry->digital_product_id,
                            'digital_product_name' => $entry->digital_product_name ?? $digitalProduct?->name,
                            'digital_product_sku' => $entry->digital_product_sku ?? $digitalProduct?->sku,
                            'digital_product_brand' => $entry->digital_product_brand ?? $digitalProduct?->brand?->name,
                            'code_value' => $voucher?->code ? $this->voucherCipherService->safeDecrypt($voucher->code) : null,
                            'pin_code_value' => $voucher?->pin_code ? $this->voucherCipherService->safeDecrypt($voucher->pin_code) : null,
                            'voucher_status' => $voucher?->status,
                            'allocated_at' => $entry->created_at?->toISOString(),
                            'voucher_created_at' => $voucher?->created_at?->toISOString(),
                            'voucher_updated_at' => $voucher?->updated_at?->toISOString(),
                        ];
                    });
            })
            ->values();
    }
}
