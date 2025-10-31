<?php

namespace App\Repositories;

use App\Services\VoucherImportService;

class VoucherRepository
{
    public function __construct(private VoucherImportService $voucherImportService) {}

    public function importVouchers(array $data): bool
    {
        return $this->voucherImportService->processFile($data);
    }
}
