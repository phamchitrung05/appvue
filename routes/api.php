<?php

use App\Http\Controllers\Api\DiningTablesController;
use App\Http\Controllers\Api\OrderItemsController;
use App\Http\Controllers\Api\OrdersController;
use App\Http\Controllers\Api\PaymentsController;
use App\Http\Controllers\Api\PrintersController;
use App\Http\Controllers\Api\ProductGroupsController;
use App\Http\Controllers\Api\ProductsController;
use App\Http\Controllers\Api\StoresController;
use App\Http\Controllers\Api\TableSessionsController;
use App\Http\Controllers\Api\TableZonesController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::apiResource('stores', StoresController::class);
    Route::apiResource('table-zones', TableZonesController::class);
    Route::apiResource('product-groups', ProductGroupsController::class);
    Route::apiResource('products', ProductsController::class);
    // Sơ đồ bàn cho màn hình Order/List — phải đăng ký TRƯỚC apiResource
    // để 'floor' không bị tham số {dining_table} của route show nuốt mất.
    Route::get('dining-tables/floor', [DiningTablesController::class, 'floor']);
    Route::apiResource('dining-tables', DiningTablesController::class);
    Route::apiResource('table-sessions', TableSessionsController::class);
    Route::apiResource('orders', OrdersController::class);
    Route::apiResource('order-items', OrderItemsController::class);
    Route::apiResource('payments', PaymentsController::class);
    Route::apiResource('printers', PrintersController::class);
});
