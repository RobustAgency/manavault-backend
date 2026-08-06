<?php

namespace App\Integrations;

use App\Actions\Gift2Games\GetProduct;
use App\Actions\Gift2Games\CreateOrder;
use App\Services\Voucher\VoucherCipherService;
use App\Services\Gift2Games\SyncDigitalProducts;

class Gift2GamesGbp extends Gift2Games
{
    public function __construct(
        CreateOrder $createOrder,
        GetProduct $getProduct,
        SyncDigitalProducts $syncDigitalProducts,
        VoucherCipherService $voucherCipherService,
    ) {
        parent::__construct(
            'gift-2-games-gbp',
            $createOrder,
            $getProduct,
            $syncDigitalProducts,
            $voucherCipherService,
        );
    }
}
