<?php

namespace App\Integrations;

use App\Actions\Gift2Games\CreateOrder;
use App\Services\Voucher\VoucherCipherService;
use App\Services\Gift2Games\SyncDigitalProducts;
use App\Services\PurchaseOrder\PurchaseOrderStatusService;

class Gift2GamesUsd extends Gift2Games
{
    public function __construct(
        CreateOrder $createOrder,
        SyncDigitalProducts $syncDigitalProducts,
        VoucherCipherService $voucherCipherService,
        PurchaseOrderStatusService $purchaseOrderStatusService,
    ) {
        parent::__construct(
            'gift2games',
            $createOrder,
            $syncDigitalProducts,
            $voucherCipherService,
            $purchaseOrderStatusService,
        );
    }
}
