<?php

namespace App\Providers;

use App\Events\SaleOrderCompleted;
use App\Events\NewVouchersAvailable;
use App\Events\PurchaseOrderItemUpdated;
use App\Listeners\ProcessVoucherCodes;
use App\Listeners\DispatchNewVouchersOnFulfillment;
use App\Listeners\SyncSaleOrderDetailOnFulfillment;
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
        PurchaseOrderItemUpdated::class => [
            DispatchNewVouchersOnFulfillment::class,
        ],
    ];
}
