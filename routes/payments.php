<?php

use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\StandalonePaymentRequestController;
use App\Http\Controllers\Public\PaymentController;
use App\Http\Controllers\Public\PaymentRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/pay', [PaymentController::class, 'create'])->name('pay');
Route::post('/pay', [PaymentController::class, 'store'])->middleware('throttle:payments')->name('pay.store');
Route::get('/pay/request/{paymentRequest:public_id}', [PaymentRequestController::class, 'show'])->middleware(['signed', 'throttle:20,1'])->name('pay.request.show');
Route::post('/pay/request/{paymentRequest:public_id}', [PaymentRequestController::class, 'pay'])->middleware(['signed', 'throttle:20,1'])->name('pay.request.pay');
Route::get('/pay/request/{paymentRequest:public_id}/checkout', [PaymentRequestController::class, 'checkout'])->middleware(['signed', 'throttle:20,1'])->name('pay.request.checkout');
Route::get('/pay/checkout/{payment}', [PaymentController::class, 'checkout'])->middleware('signed')->name('pay.checkout');
Route::get('/pay/return/{reference}', [PaymentController::class, 'gatewayReturn'])->middleware('throttle:30,1')->name('pay.return');
Route::get('/pay/receipt/{payment}', [PaymentController::class, 'receipt'])->middleware('signed')->name('pay.receipt');
Route::post('/payments/webhook/{gateway}', [PaymentController::class, 'webhook'])->middleware('throttle:120,1')->name('pay.webhook');

Route::prefix('admin/payments')->name('admin.payments.')->middleware(['auth', 'can:manage-payments'])->group(function () {
    Route::get('/', [AdminPaymentController::class, 'index'])->name('index');
    Route::get('/requests', [StandalonePaymentRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/create', [StandalonePaymentRequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [StandalonePaymentRequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{paymentRequest}', [StandalonePaymentRequestController::class, 'show'])->name('requests.show');
    Route::post('/requests/{paymentRequest}/resend', [StandalonePaymentRequestController::class, 'resend'])->name('requests.resend');
    Route::post('/requests/{paymentRequest}/cancel', [StandalonePaymentRequestController::class, 'cancel'])->name('requests.cancel');
    Route::get('/offline/create', [AdminPaymentController::class, 'createOffline'])->middleware('can:manage-settings')->name('offline.create');
    Route::post('/offline', [AdminPaymentController::class, 'storeOffline'])->middleware('can:manage-settings')->name('offline.store');
    Route::get('/{payment}', [AdminPaymentController::class, 'show'])->name('show');
    Route::post('/{payment}/offline-refund', [AdminPaymentController::class, 'refundOffline'])->middleware('can:manage-settings')->name('offline.refund');
});
