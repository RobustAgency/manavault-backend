<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\SyncTikkeryProductsCommand;
use App\Console\Commands\FetchTikkeryVouchersCommand;
use App\Console\Commands\SyncSupplierProductsCommand;
use App\Console\Commands\SyncGift2GamesProductsCommand;
use App\Console\Commands\SyncIrewardifyProductsCommand;
use App\Console\Commands\FetchIrewardifyVouchersCommand;
use App\Console\Commands\AddVoucherCodeForEZCardsCommand;
use App\Console\Commands\PlacePendingPurchaseOrdersCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SyncSupplierProductsCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(AddVoucherCodeForEZCardsCommand::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(PlacePendingPurchaseOrdersCommand::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(SyncIrewardifyProductsCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(FetchIrewardifyVouchersCommand::class)
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(SyncGift2GamesProductsCommand::class)
    ->hourly()
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
