<?php

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
});
