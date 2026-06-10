<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\UpdatePurchaseOrderItems;
use App\Console\Commands\SyncTikkeryProductsCommand;
use App\Console\Commands\FetchTikkeryVouchersCommand;
use App\Console\Commands\SyncSupplierProductsCommand;
use App\Console\Commands\SyncIrewardifyProductsCommand;
use App\Console\Commands\FetchIrewardifyVouchersCommand;
use App\Console\Commands\PlacePendingPurchaseOrdersCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SyncSupplierProductsCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(UpdatePurchaseOrderItems::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(PlacePendingPurchaseOrdersCommand::class)
    ->everyMinute()
    ->withoutOverlapping(expiresAt: 120) // 2 minutes
    ->runInBackground();

Schedule::command(SyncIrewardifyProductsCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(FetchIrewardifyVouchersCommand::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(SyncTikkeryProductsCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(FetchTikkeryVouchersCommand::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
