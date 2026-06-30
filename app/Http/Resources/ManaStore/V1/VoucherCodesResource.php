<?php

namespace App\Http\Resources\ManaStore\V1;

use App\Models\SaleOrder;
use App\Services\Voucher\VoucherCipherService;

class VoucherCodesResource
{
    /**
     * Format voucher codes grouped by product for a sale order.
     *
     * @return array<int, array{title: string, status: string, codes: array}>
     */
    public static function format(SaleOrder $saleOrder): array
    {
        $voucherCipherService = app(VoucherCipherService::class);
        $formattedCodes = [];

        foreach ($saleOrder->items as $item) {
            $codes = [];

            foreach ($item->digitalProducts as $digitalProduct) {
                if ($digitalProduct->voucher) {
                    $code = $digitalProduct->voucher->code;

                    // Decrypt the code if it's encrypted
                    if ($voucherCipherService->isEncrypted($code)) {
                        $code = $voucherCipherService->decryptCode($code);
                    }

                    $codes[] = [
                        'code' => $code,
                        'serial_number' => $digitalProduct->voucher->serial_number,
                        'pin_code' => $digitalProduct->voucher->pin_code,
                    ];
                }
            }

            $formattedCodes[] = [
                'id' => $item->product_id,
                'title' => $item->product->name,
                'status' => $item->voucherFulfillmentStatus()->value,
                'codes' => $codes,
            ];
        }

        return $formattedCodes;
    }
}
