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
    Route::get('/dat-mon', function (Request $request) {
        $pc = $request->attributes->get('current_pc');

        $categories = Category::where('is_active', true)
            ->with(['products' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('name')
                    ->select([
                    'id',
                    'category_id',
                    'name',
                    'description',
                    'unit',
                    'selling_price',
                    'image_path',
                    'stock_quantity',
                ]);
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('Customer/Order', [
            'pc' => $pc ? [
                'id' => $pc->id,
                'code' => $pc->code,
                'name' => $pc->name,
            ] : [
                'id' => null,
                'code' => 'DEV',
                'name' => 'Máy kiểm tra',
            ],
            'categories' => $categories,
        ]);

    })->name('customer.order');

    Route::post('/dat-mon', [CustomerOrderController::class, 'store'])
    ->name('customer.order.store');
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

    Route::get('/orders', [OrderController::class, 'index'])
    ->name('orders.index');
    Route::post('/orders/{id}/confirm', [OrderController::class, 'confirm'])
    ->name('orders.confirm');

    Route::post('/orders/{id}/complete', [OrderController::class, 'complete'])
    ->name('orders.complete');

    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancel'])
    ->name('orders.cancel');

    Route::post('/inventory', [InventoryController::class, 'store'])
    ->name('inventory.store');

    Route::post('/orders/{id}/payments', [PaymentController::class, 'store'])
    ->name('orders.payments.store');

    Route::get('/reports', [ReportController::class, 'index'])
    ->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])
    ->name('reports.export');
    Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf'])
    ->name('reports.export-pdf');

    Route::post('/orders/manual', [OrderController::class, 'store'])
    ->name('orders.store');

    Route::post('/products/{product}/toggle-status', [
        ProductController::class,
        'toggleStatus',
    ])->name('products.toggle-status');

});

require __DIR__.'/settings.php';
