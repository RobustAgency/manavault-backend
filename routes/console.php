<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\SyncEzCardsProductsCommand;
use App\Console\Commands\SyncTikkeryProductsCommand;
use App\Console\Commands\FetchTikkeryVouchersCommand;
use App\Console\Commands\SyncGift2GamesProductsCommand;
use App\Console\Commands\FetchIrewardifyVouchersCommand;
use App\Console\Commands\AddVoucherCodeForEZCardsCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(AddVoucherCodeForEZCardsCommand::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(FetchIrewardifyVouchersCommand::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(SyncEzCardsProductsCommand::class)
    ->hourly()
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
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();
Schedule::command(SyncGift2GamesProductsCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();
