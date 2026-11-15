<?php

use App\Http\Middleware\IdentifyPc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\InventoryController;
use App\Models\Category;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\CallOwnerController;


Route::inertia('/', 'Welcome')->name('home');

// Khu vực khách đặt món trên máy tính
Route::post('/send-telegram', [CallOwnerController::class, 'send'])
    ->name('call-owner.send');

Route::middleware([IdentifyPc::class])->group(function () {
    Route::get('/dat-mon', [CustomerOrderController::class, 'index'])
        ->name('customer.order');

    Route::post('/dat-mon', [CustomerOrderController::class, 'store'])
        ->name('customer.order.store');

    Route::post('/thanh-toan-gop', [CustomerOrderController::class, 'createGroupPayment'])
        ->name('customer.order.group-payment');
});


// Khu vực nhân viên
Route::middleware(['auth', 'admin'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::resource('products', ProductController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('categories', CategoryController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::get('/inventory', [InventoryController::class, 'index'])
        ->name('inventory.index');

    // Quản lý đơn hàng
    Route::get('/orders', [OrderController::class, 'index'])
        ->name('orders.index');

    Route::post('/orders/{id}/confirm', [OrderController::class, 'confirm'])
        ->name('orders.confirm');

    Route::post('/orders/{id}/complete', [OrderController::class, 'complete'])
        ->name('orders.complete');

    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])
        ->name('orders.cancel');

    // Xác nhận đã nạp tiền thủ công vào tài khoản khách
    Route::post('/orders/{id}/confirm-topup', [OrderController::class, 'confirmTopup'])
        ->name('orders.confirm-topup');

    // Thanh toán đơn hàng
    Route::post('/orders/{id}/payments', [PaymentController::class, 'store'])
        ->name('orders.payments.store');

    // Tạo đơn hàng thủ công
    Route::post('/orders/manual', [OrderController::class, 'store'])
        ->name('orders.store');

    // Kho hàng
    Route::post('/inventory', [InventoryController::class, 'store'])
        ->name('inventory.store');

    // Báo cáo
    Route::get('/reports', [ReportController::class, 'index'])
        ->name('reports.index');

    Route::get('/reports/export', [ReportController::class, 'export'])
        ->name('reports.export');

    Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf'])
        ->name('reports.export-pdf');

    Route::post('/products/{product}/toggle-status', [
        ProductController::class,
        'toggleStatus',
    ])->name('products.toggle-status');
});

require __DIR__.'/settings.php';