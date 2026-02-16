<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ManaStore\V1\ProductController;
use App\Http\Controllers\Api\ManaStore\V1\SaleOrderController;

Route::prefix('/v1')->group(function () {

    Route::get('/status', function () {
        return response()->json(['status' => 'ManaStore API is operational']);
    });

    Route::middleware(['manastore.auth'])->group(function () {
        Route::prefix('/products')->group(function () {
            Route::get('', [ProductController::class, 'index']);
            Route::get('/{product}', [ProductController::class, 'show']);
        });

        Route::prefix('/sale-orders')->group(function () {
            Route::post('', [SaleOrderController::class, 'store']);
            Route::get('/{saleOrder}/vouchers', [SaleOrderController::class, 'getVoucherCodes']);
        });
    });
});
