<?php

namespace App\Http\Requests\Voucher;

use App\Services\Voucher\VoucherFileImportService;
use App\Services\Voucher\VoucherPurchaseOrderValidator;

class VoucherCreateService
{
    public function __construct(
        private VoucherPurchaseOrderValidator $voucherPurchaseOrderValidator,
        private VoucherFileImportService $voucherFileImportService
    ) {}

    public function createVoucher(array $data)
    {
        if ($data['file']) {
            $this->voucherFileImportService->processFile($data);
        } else {

        }
    }
}
