<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EtimsController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MpesaController;
use App\Http\Controllers\PackagingController;
use App\Http\Controllers\PackController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReceiveController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StockTakeController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Selling (all staff)
    Route::get('/pos', [PosController::class, 'index'])->name('pos');
    Route::post('/pos/shift', [PosController::class, 'openShift'])->name('pos.shift');
    Route::get('/pos/lookup', [PosController::class, 'lookup'])->name('pos.lookup');
    Route::get('/pos/products', [PosController::class, 'products'])->name('pos.products');
    Route::get('/pos/products/{product}', [PosController::class, 'options'])->name('pos.options');
    Route::get('/pos/customers', [PosController::class, 'customers'])->name('pos.customers');
    Route::post('/pos/customers', [PosController::class, 'storeCustomer'])->name('pos.customers.store');
    Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');

    Route::post('/pos/mpesa/stk', [MpesaController::class, 'stk'])->name('pos.mpesa.stk');
    Route::get('/pos/mpesa/recent', [MpesaController::class, 'recent'])->name('pos.mpesa.recent');
    Route::get('/pos/mpesa/{transaction}', [MpesaController::class, 'status'])->name('pos.mpesa.status');

    Route::get('/receipts/{sale}', [ReceiptController::class, 'show'])->name('receipts.show');

    Route::get('/returns', [ReturnController::class, 'index'])->name('returns');
    Route::post('/returns/{sale}', [ReturnController::class, 'store'])->name('returns.store');
    Route::post('/returns/{sale}/void', [ReturnController::class, 'void'])->name('returns.void');
    Route::get('/returns/{saleReturn}/receipt', [ReturnController::class, 'receipt'])->name('returns.receipt');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::post('/customers/{customer}/payments', [CustomerController::class, 'payment'])->name('customers.payment');

    Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::get('/expenses/verify-mpesa', [ExpenseController::class, 'verifyMpesa'])->name('expenses.verify-mpesa');
    Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');

    Route::get('/shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');
    Route::post('/shifts/{shift}/close', [ShiftController::class, 'storeClose'])->name('shifts.close.store');
    Route::get('/shifts/{shift}/report', [ShiftController::class, 'report'])->name('shifts.report');

    // Back office (admins and managers)
    Route::middleware('manager')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::patch('/products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');

        Route::get('/products/{product}/packaging', [PackagingController::class, 'index'])->name('products.packaging');
        Route::post('/products/{product}/packaging', [PackagingController::class, 'store'])->name('products.packaging.store');
        Route::patch('/products/{product}/packaging/{unit}', [PackagingController::class, 'update'])->name('products.packaging.update');
        Route::patch('/products/{product}/packaging/{unit}/default', [PackagingController::class, 'makeDefault'])->name('products.packaging.default');
        Route::patch('/products/{product}/packaging/{unit}/toggle', [PackagingController::class, 'toggle'])->name('products.packaging.toggle');

        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
        Route::post('/stock/{product}/adjust', [StockController::class, 'adjust'])->name('stock.adjust');

        Route::get('/stock-takes', [StockTakeController::class, 'index'])->name('stock-takes.index');
        Route::post('/stock-takes', [StockTakeController::class, 'store'])->name('stock-takes.store');
        Route::get('/stock-takes/{stockTake}', [StockTakeController::class, 'show'])->name('stock-takes.show');
        Route::post('/stock-takes/{stockTake}/count', [StockTakeController::class, 'count'])->name('stock-takes.count');
        Route::post('/stock-takes/{stockTake}/complete', [StockTakeController::class, 'complete'])->name('stock-takes.complete');
        Route::post('/stock-takes/{stockTake}/cancel', [StockTakeController::class, 'cancel'])->name('stock-takes.cancel');

        Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
        Route::get('/transfers/create', [TransferController::class, 'create'])->name('transfers.create');
        Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::get('/transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
        Route::post('/transfers/{transfer}/receive', [TransferController::class, 'receive'])->name('transfers.receive');
        Route::post('/transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');

        Route::get('/stock/receive', [ReceiveController::class, 'create'])->name('stock.receive');
        Route::get('/stock/receive/lookup', [ReceiveController::class, 'lookup'])->name('stock.receive.lookup');
        Route::post('/stock/receive', [ReceiveController::class, 'store'])->name('stock.receive.store');

        Route::get('/stock/packs/{product}', [PackController::class, 'index'])->name('stock.packs');
        Route::post('/stock/packs/{product}', [PackController::class, 'store'])->name('stock.packs.store');
        Route::patch('/stock/packs/{product}/{pack}', [PackController::class, 'update'])->name('stock.packs.update');
        Route::patch('/stock/packs/{product}/{pack}/toggle', [PackController::class, 'toggle'])->name('stock.packs.toggle');
        Route::post('/stock/packs/{product}/repack', [PackController::class, 'repack'])->name('stock.packs.repack');

        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::post('/sales/payments/{payment}/verify', [SaleController::class, 'verifyPayment'])->name('sales.payments.verify');

        Route::get('/shifts', [ShiftController::class, 'index'])->name('shifts.index');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');

        Route::get('/etims', [EtimsController::class, 'index'])->name('etims.index');
        Route::post('/etims/retry-all', [EtimsController::class, 'retryAll'])->name('etims.retry-all');
        Route::post('/etims/{invoice}/retry', [EtimsController::class, 'retry'])->name('etims.retry');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    });
});

