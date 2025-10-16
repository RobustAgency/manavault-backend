<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SupplierController;

// Other admin controllers can be imported here as needed
Route::middleware(['auth:supabase', 'role:admin'])->group(function () {
    Route::prefix('/admin')->group(function () {
        Route::prefix('/users')->controller(UserController::class)->group(function () {
            Route::get('', 'index');
            Route::get('/search', 'search');
            Route::get('/{user}', 'show');
            Route::post('/{user}/approve', 'approve');
            Route::post('/{user}/revoke-approval', 'revokeApproval');
        });

        Route::prefix('/suppliers')->controller(SupplierController::class)->group(function () {
            Route::get('', 'index');
            Route::post('', 'store');
            Route::get('/{supplier}', 'show');
            Route::post('/{supplier}', 'update');
            Route::delete('/{supplier}', 'destroy');
        });
    });
});
