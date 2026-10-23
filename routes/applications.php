<?php

use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController;
use App\Http\Controllers\Admin\EnquiryController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/apply', [ApplicationController::class, 'create'])->name('apply');
Route::post('/apply', [ApplicationController::class, 'store'])->middleware(['throttle:applications', 'captcha'])->name('apply.store');
Route::get('/apply/received', [ApplicationController::class, 'success'])->name('apply.success');
Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware(['throttle:contact', 'captcha'])->name('contact.store');

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function (): void {
    Route::middleware('can:manage-applications')->group(function (): void {
        Route::get('applications', [AdminApplicationController::class, 'index'])->name('applications.index');
        Route::get('applications/{application}', [AdminApplicationController::class, 'show'])->name('applications.show');
        Route::patch('applications/{application}', [AdminApplicationController::class, 'update'])->name('applications.update');
        Route::get('documents/{document}/download', [AdminApplicationController::class, 'download'])->name('documents.download');
    });
    Route::middleware('can:manage-enquiries')->group(function (): void {
        Route::get('enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
        Route::get('enquiries/{enquiry}', [EnquiryController::class, 'show'])->name('enquiries.show');
        Route::patch('enquiries/{enquiry}', [EnquiryController::class, 'update'])->name('enquiries.update');
    });
});
