<?php

use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Public\PaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/pay', [PaymentController::class, 'create'])->name('pay');
Route::post('/pay', [PaymentController::class, 'store'])->middleware(['throttle:payments', 'captcha'])->name('pay.store');
Route::get('/pay/receipt/{payment}', [PaymentController::class, 'receipt'])->middleware('signed')->name('pay.receipt');
Route::post('/payments/webhook/{gateway}', [PaymentController::class, 'webhook'])->middleware('throttle:120,1')->name('pay.webhook');

Route::prefix('admin/payments')->name('admin.payments.')->middleware(['auth', 'can:manage-payments'])->group(function () {
    Route::get('/', [AdminPaymentController::class, 'index'])->name('index');
    Route::get('/offline/create', [AdminPaymentController::class, 'createOffline'])->middleware('can:manage-settings')->name('offline.create');
    Route::post('/offline', [AdminPaymentController::class, 'storeOffline'])->middleware('can:manage-settings')->name('offline.store');
    Route::get('/{payment}', [AdminPaymentController::class, 'show'])->name('show');
    Route::patch('/{payment}/application', [AdminPaymentController::class, 'link'])->name('link');
    Route::post('/{payment}/offline-refund', [AdminPaymentController::class, 'refundOffline'])->middleware('can:manage-settings')->name('offline.refund');
});
