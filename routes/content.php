<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Public\NewsController;
use App\Http\Controllers\Public\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/news', [NewsController::class, 'index'])->name('news');
Route::get('/news/images/{post}', [NewsController::class, 'image'])->name('news.image');
Route::get('/news/media/{media}', [NewsController::class, 'media'])->name('news.media');
Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');
foreach (['privacy-policy', 'terms', 'payment-terms', 'refund-policy', 'disclaimer'] as $slug) {
    Route::get('/'.$slug, [PageController::class, 'show'])->name($slug);
}
Route::middleware(['auth', 'can:manage-content'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('posts', PostController::class)->except('show');
    Route::delete('/posts/{post}/media/{media}', [PostController::class, 'destroyMedia'])->name('posts.media.destroy');
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('pages', AdminPageController::class)->only(['index', 'edit', 'update']);
});
