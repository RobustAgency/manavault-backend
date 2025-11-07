<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\VoucherController;


Route::middleware(['auth:supabase', 'role:super_admin'])->group(function () {
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

        Route::prefix('/products')->group(function () {
            Route::get('', [ProductController::class, 'index']);
            Route::post('', [ProductController::class, 'store']);
            Route::get('/third-party', [ProductController::class, 'listThirdPartyProducts']);
            Route::get('/{product}', [ProductController::class, 'show']);
            Route::post('/{product}', [ProductController::class, 'update']);
            Route::delete('/{product}', [ProductController::class, 'destroy']);
        });

        Route::prefix('/purchase-orders')->controller(PurchaseOrderController::class)->group(function () {
            Route::get('', 'index');
            Route::post('', 'store');
            Route::get('/{purchaseOrder}', 'show');
        });

        Route::prefix('/vouchers')->controller(VoucherController::class)->group(function () {
            Route::post('import', 'importVouchers');
        });
    });
});
