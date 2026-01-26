<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\SyncEzCardsProductsCommand;
use App\Console\Commands\SyncGift2GamesProductsCommand;
use App\Console\Commands\AddVoucherCodeForEZCardsCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(AddVoucherCodeForEZCardsCommand::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(SyncEzCardsProductsCommand::class)
    ->daily()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command(SyncGift2GamesProductsCommand::class)
    ->daily()
    ->withoutOverlapping()
    ->runInBackground();
