<?php

namespace App\Providers;

use App\Events\SaleOrderCompleted;
use App\Events\NewVouchersAvailable;
use App\Events\DigitalProductUpdated;
use App\Listeners\ProcessVoucherCodes;
use App\Listeners\SyncSaleOrderDetailOnFulfillment;
use App\Listeners\SyncProductsOnDigitalProductUpdate;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        NewVouchersAvailable::class => [
            ProcessVoucherCodes::class,
        ],
        SaleOrderCompleted::class => [
            SyncSaleOrderDetailOnFulfillment::class,
        ],
        DigitalProductUpdated::class => [
            SyncProductsOnDigitalProductUpdate::class,
        ],
    ];
}
