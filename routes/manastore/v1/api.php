<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ManaStore\V1\ProductController;

Route::middleware(['manastore.auth'])->group(function () {
    Route::prefix('/v1')->group(function () {
        Route::get('/status', function () {
            return response()->json(['status' => 'ManaStore API is operational']);
        });

        Route::prefix('/products')->group(function () {
            Route::get('', [ProductController::class, 'index']);
        });
    });
});
