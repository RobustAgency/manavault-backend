<?php

namespace App\Providers;

use App\Events\NewVouchersAvailable;
use App\Events\DigitalProductCreated;
use App\Events\DigitalProductUpdated;
use App\Events\DigitalProductDeleting;
use App\Listeners\ProcessVoucherCodes;
use App\Events\DigitalProductsDeactivated;
use App\Listeners\SyncProductsOnDigitalProductUpdate;
use App\Listeners\SyncProductsOnDigitalProductCreated;
use App\Listeners\SyncProductsOnDigitalProductDeleting;
use App\Listeners\SyncProductsOnDigitalProductsDeactivated;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        NewVouchersAvailable::class => [
            ProcessVoucherCodes::class,
        ],
        DigitalProductCreated::class => [
            SyncProductsOnDigitalProductCreated::class,
        ],
        DigitalProductUpdated::class => [
            SyncProductsOnDigitalProductUpdate::class,
        ],
        DigitalProductDeleting::class => [
            SyncProductsOnDigitalProductDeleting::class,
        ],
        DigitalProductsDeactivated::class => [
            SyncProductsOnDigitalProductsDeactivated::class,
        ],
    ];
}
