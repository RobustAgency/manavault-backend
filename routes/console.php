<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\UpdatePurchaseOrderItems;
use App\Console\Commands\SyncSupplierProductsCommand;
use App\Console\Commands\PlacePendingPurchaseOrdersCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SyncSupplierProductsCommand::class)
    ->hourly()
    ->withoutOverlapping(expiresAt: 2) // 2 minutes
    ->runInBackground();

Schedule::command(UpdatePurchaseOrderItems::class)
    ->everyMinute()
    ->withoutOverlapping(expiresAt: 5) // 5 minutes
    ->runInBackground();

Schedule::command(PlacePendingPurchaseOrdersCommand::class)
    ->everyMinute()
    ->withoutOverlapping(expiresAt: 5) // 5 minutes
    ->runInBackground();
