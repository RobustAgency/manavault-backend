<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\PriceRuleController;
use App\Http\Controllers\Admin\SaleOrderController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\SupplierKpiController;
use App\Http\Controllers\Admin\DigitalStockController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\DigitalProductController;
use App\Http\Controllers\Admin\VoucherAuditLogController;

Route::middleware(['auth:supabase', 'user.approved'])->group(function () {
    Route::prefix('profile')->controller(ProfileController::class)->group(function () {
        Route::get('', 'show');
        Route::get('/user-info', 'userInfo');
    });

    Route::prefix('/users')->controller(UserController::class)->group(function () {
        Route::get('', 'index')->middleware('permission:view_user');
        Route::post('', 'store')->middleware('permission:create_user');
        Route::get('/{user}', 'show')->middleware('permission:view_user');
        Route::post('/{user}/approve', 'approve')->middleware('permission:edit_user');
        Route::post('/{user}/revoke-approval', 'revokeApproval')->middleware('permission:edit_user');
        Route::post('/{user}/assign-roles', 'assignRoles')->middleware('permission:edit_user');
        Route::delete('/{user}', 'destroy')->middleware('permission:delete_user');
    });

    Route::prefix('/suppliers')->group(function () {
        Route::get('/kpis', [SupplierKpiController::class, 'index'])->middleware('permission:view_supplier_kpi');

        Route::controller(SupplierController::class)->group(function () {
            Route::get('', 'index')->middleware('permission:view_supplier');
            Route::post('', 'store')->middleware('permission:create_supplier');
            Route::get('/{supplier}', 'show')->middleware('permission:view_supplier');
            Route::post('/{supplier}', 'update')->middleware('permission:edit_supplier');
            Route::delete('/{supplier}', 'destroy')->middleware('permission:delete_supplier');
        });
    });

    Route::prefix('/products')->group(function () {
        Route::get('', [ProductController::class, 'index'])->middleware('permission:view_product');
        Route::post('', [ProductController::class, 'store'])->middleware('permission:create_product');
        Route::post('/batch-import', [ProductController::class, 'batchImport'])->middleware('permission:create_product');
        Route::get('/{product}', [ProductController::class, 'show'])->middleware('permission:view_product');
        Route::post('/{product}', [ProductController::class, 'update'])->middleware('permission:edit_product');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->middleware('permission:delete_product');
        Route::post('/{product}/digital-products/priority', [ProductController::class, 'updateDigitalProductsPriority'])->middleware('permission:edit_product');
        Route::post('/{product}/digital_products', [ProductController::class, 'assignDigitalProducts'])->middleware('permission:edit_product');
        Route::delete('/{product}/digital_products/{digitalProductId}', [ProductController::class, 'removeDigitalProduct'])->middleware('permission:edit_product');
    });

    Route::prefix('/digital-products')->controller(DigitalProductController::class)->group(function () {
        Route::get('', 'index')->middleware('permission:view_digital_stock');
        Route::post('', 'store')->middleware('permission:create_digital_stock');
        Route::post('/batch-import', 'batchImport')->middleware('permission:create_digital_stock');
        Route::get('/{digitalProduct}', 'show')->middleware('permission:view_digital_stock');
        Route::post('/{digitalProduct}', 'update')->middleware('permission:edit_digital_stock');
        Route::delete('/{digitalProduct}', 'destroy')->middleware('permission:delete_digital_stock');
    });

    Route::prefix('/purchase-orders')->controller(PurchaseOrderController::class)->group(function () {
        Route::get('', 'index')->middleware('permission:view_purchase_order');
        Route::post('', 'store')->middleware('permission:create_purchase_order');
        Route::get('/{purchaseOrder}', 'show')->middleware('permission:view_purchase_order');
        Route::post('/{purchaseOrder}/vouchers', 'purchaseOrderVouchers')->middleware('permission:edit_purchase_order');
    });

    Route::prefix('/vouchers')->controller(VoucherController::class)->group(function () {
        Route::get('', 'index')->middleware('permission:view_voucher');
        Route::post('store', 'store')->middleware('permission:create_voucher');
        Route::post('/{voucher}/code', 'show')->middleware('permission:view_voucher');
    });

    Route::prefix('/digital-stocks')->controller(DigitalStockController::class)->group(function () {
        Route::get('', 'index')->middleware('permission:view_digital_stock');
        Route::get('/low-stock', 'lowStockProducts')->middleware('permission:view_digital_stock');
    });

    Route::prefix('/brands')->controller(BrandController::class)->group(function () {
        Route::get('', 'index')->middleware('permission:view_brand');
        Route::post('', 'store')->middleware('permission:create_brand');
        Route::get('/{brand}', 'show')->middleware('permission:view_brand');
        Route::match(['put', 'post'], '/{brand}', 'update')->middleware('permission:edit_brand');
        Route::delete('/{brand}', 'destroy')->middleware('permission:delete_brand');
    });

    Route::prefix('/voucher-audit-logs')->controller(VoucherAuditLogController::class)->group(function () {
        Route::get('', 'index')->middleware('permission:view_voucher_audit_log');
    });

    Route::prefix('/activity-logs')->controller(ActivityLogController::class)->group(function () {
        Route::get('', 'index')->middleware('permission:view_activity_log');
    });

    Route::prefix('/price-rules')->controller(PriceRuleController::class)->group(function () {
        Route::get('', 'index')->middleware('permission:view_price_rule');
        Route::post('', 'store')->middleware('permission:create_price_rule');
        Route::post('/preview', 'preview')->middleware('permission:view_price_rule');
        Route::get('/{priceRule}', 'show')->middleware('permission:view_price_rule');
        Route::get('/{priceRule}/digital-products', 'postViewDigitalProducts')->middleware('permission:view_price_rule');
        Route::post('/{priceRule}', 'update')->middleware('permission:edit_price_rule');
        Route::delete('/{priceRule}', 'destroy')->middleware('permission:delete_price_rule');
    });

    Route::prefix('/sale-orders')->controller(SaleOrderController::class)->group(function () {
        Route::get('', 'index')->middleware('permission:view_sale_order');
        Route::get('/{saleOrder}', 'show')->middleware('permission:view_sale_order');
        Route::get('/{saleOrder}/codes', 'codes')->middleware('permission:view_sale_order');
        Route::get('/{saleOrder}/codes/download', 'downloadOrderCodes')->middleware('permission:view_sale_order');
    });

    Route::prefix('/roles')->controller(RoleController::class)->group(function () {
        Route::get('', 'index')->middleware('permission:view_role');
        Route::post('', 'store')->middleware('permission:create_role');
        Route::get('/{role}', 'show')->middleware('permission:view_role');
        Route::post('/{role}', 'update')->middleware('permission:edit_role');
        Route::delete('/{role}', 'destroy')->middleware('permission:delete_role');
    });

    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:view_permission');

    Route::get('/modules', [ModuleController::class, 'index'])->middleware('permission:view_module');
});
