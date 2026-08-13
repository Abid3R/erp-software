<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Print-optimized document views (browser-rendered → correct Bangla shaping).
Route::middleware(['web', 'auth'])->prefix('print')->name('print.')->group(function () {
    Route::get('payment/{payment}', [PrintController::class, 'payment'])->name('payment');
    Route::get('journal/{journal}', [PrintController::class, 'journal'])->name('journal');
    Route::get('roster/{roster}', [PrintController::class, 'roster'])->name('roster');
    Route::get('payslip/{payslip}', [PrintController::class, 'payslip'])->name('payslip');
    Route::get('purchase-order/{purchaseOrder}', [PrintController::class, 'purchaseOrder'])->name('purchase-order');
    Route::get('sales-order/{salesOrder}', [PrintController::class, 'salesOrder'])->name('sales-order');
    Route::get('quotation/{quotation}', [PrintController::class, 'quotation'])->name('quotation');
    Route::get('expense/{expense}', [PrintController::class, 'expense'])->name('expense');
    Route::get('supplier-invoice/{supplierInvoice}', [PrintController::class, 'supplierInvoice'])->name('supplier-invoice');
    Route::get('goods-receipt/{goodsReceipt}', [PrintController::class, 'goodsReceipt'])->name('goods-receipt');
    Route::get('customer-statement/{customer}', [PrintController::class, 'customerStatement'])->name('customer-statement');
    Route::get('supplier-statement/{supplier}', [PrintController::class, 'supplierStatement'])->name('supplier-statement');
    Route::get('stock-adjustment/{stockAdjustment}', [PrintController::class, 'stockAdjustment'])->name('stock-adjustment');
    Route::get('stock-transfer/{stockTransfer}', [PrintController::class, 'stockTransfer'])->name('stock-transfer');
});

// DMS downloads — private files, authorised per company (see DocumentController).
Route::middleware(['web', 'auth'])
    ->get('documents/{document}/download', [DocumentController::class, 'download'])
    ->name('documents.download');
