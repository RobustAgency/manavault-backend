<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SupabaseController;
use App\Http\Controllers\LoginLogsController;
use App\Http\Controllers\Api\GifteryController;

Route::post('/auth/login', [SupabaseController::class, 'login']);

Route::get('/login-logs', [LoginLogsController::class, 'index']);
Route::post('/login-logs', [LoginLogsController::class, 'store']);

// Giftery test endpoints (no authentication required)
Route::prefix('/giftery/test')->group(function () {
    Route::post('/reserve-order', [GifteryController::class, 'testReserveOrder']);
    Route::get('/products', [GifteryController::class, 'testGetProducts']);
    Route::get('/products/{productId}', [GifteryController::class, 'testGetProductDetails']);
    Route::get('/accounts', [GifteryController::class, 'testGetAccounts']);
    Route::get('/product-items/{itemId}', [GifteryController::class, 'testGetProductItemDetails']);

});

require __DIR__.'/users/api.php';
require __DIR__.'/manastore/v1/api.php';
