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

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listGroupedOrderCodes(SaleOrder $saleOrder): Collection
    {
        return $this->listOrderCodes($saleOrder)
            ->groupBy(function (array $code): string {
                return implode(':', [
                    $code['sale_order_item_id'],
                    $code['product_id'],
                    $code['digital_product_id'],
                ]);
            })
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'sale_order_id' => $first['sale_order_id'],
                    'sale_order_item_id' => $first['sale_order_item_id'],
                    'order_number' => $first['order_number'],
                    'product_id' => $first['product_id'],
                    'product_name' => $first['product_name'],
                    'digital_product_id' => $first['digital_product_id'],
                    'digital_product_name' => $first['digital_product_name'],
                    'digital_product_sku' => $first['digital_product_sku'],
                    'digital_product_brand' => $first['digital_product_brand'],
                    'voucher_codes' => $group
                        ->map(function (array $code): array {
                            return [
                                'id' => $code['id'],
                                'voucher_id' => $code['voucher_id'],
                                'code_value' => $code['code_value'],
                                'pin_code_value' => $code['pin_code_value'],
                                'voucher_status' => $code['voucher_status'],
                                'allocated_at' => $code['allocated_at'],
                                'voucher_created_at' => $code['voucher_created_at'],
                                'voucher_updated_at' => $code['voucher_updated_at'],
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();
    }
}
