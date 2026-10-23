<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Public\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/services', [SiteController::class, 'services'])->name('services');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SiteController::class, 'robots'])->name('robots');
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [SessionController::class, 'create'])->name('login');
    Route::post('/admin/login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});
Route::post('/admin/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:access-admin'])->group(function () {
    Route::redirect('/', '/admin/dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/audit', [DashboardController::class, 'audit'])->middleware('can:manage-settings')->name('audit');
    Route::get('/settings', [SettingsController::class, 'edit'])->middleware('can:manage-settings')->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->middleware('can:manage-settings')->name('settings.update');
    Route::resource('users', UserController::class)->except(['show', 'destroy'])->middleware('can:manage-users');
});
require __DIR__.'/applications.php';
require __DIR__.'/content.php';
require __DIR__.'/payments.php';
