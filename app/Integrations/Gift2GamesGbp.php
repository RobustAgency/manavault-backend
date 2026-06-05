<?php

namespace App\Integrations;

use App\Actions\Gift2Games\CreateOrder;
use App\Services\Voucher\VoucherCipherService;
use App\Services\Gift2Games\SyncDigitalProducts;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class Gift2GamesGbp extends Gift2Games
{
    public function __construct(
        CreateOrder $createOrder,
        SyncDigitalProducts $syncDigitalProducts,
        VoucherCipherService $voucherCipherService,
        PurchaseOrderStatusService $purchaseOrderStatusService,
    ) {
        parent::__construct(
            'gift-2-games-gbp',
            $createOrder,
            $syncDigitalProducts,
            $voucherCipherService,
            $purchaseOrderStatusService,
        );
    }
}
