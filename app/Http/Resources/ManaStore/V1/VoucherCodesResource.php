<?php

namespace App\Http\Resources\ManaStore\V1;

use App\Models\SaleOrder;

class VoucherCodesResource
{
    /**
     * Format voucher codes grouped by product for a sale order.
     *
     * @return array<int, array{title: string, codes: array}>
     */
    public static function format(SaleOrder $saleOrder): array
    {
        $formattedCodes = [];

        foreach ($saleOrder->items as $item) {
            $codes = [];

            foreach ($item->digitalProducts as $digitalProduct) {
                if ($digitalProduct->voucher) {
                    $codes[] = [
                        'code' => $digitalProduct->voucher->code,
                        'serial_number' => $digitalProduct->voucher->serial_number,
                        'pin_code' => $digitalProduct->voucher->pin_code,
                    ];
                }
            }

            if (! empty($codes)) {
                $formattedCodes[] = [
                    'title' => $item->product->name,
                    'codes' => $codes,
                ];
            }
        }

        return $formattedCodes;
    }
}
