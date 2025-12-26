<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['manastore.auth'])->group(function () {
    Route::prefix('/v1')->group(function () {
        Route::get('/status', function () {
            return response()->json(['status' => 'ManaStore API is operational']);
        });
    });
});
