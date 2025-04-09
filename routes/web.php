<?php

use App\Http\Controllers\Admin\FlipbookController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/admin/books', [FlipbookController::class, 'index'])->name('books.index');

    Route::get('/dashboard', [AdminController::class, 'index'])->name('dashboard');
    Route::get('/books/upload', [FlipbookController::class, 'create'])->name('books.upload.form');
    Route::post('/books/upload', [FlipbookController::class, 'store'])->name('flipbooks.upload');

    Route::post('/flipbooks/store-metadata', [FlipbookController::class, 'storeFlipbookMetadata'])
    ->name('flipbooks.store-metadata');
});

Route::post('/s3-presign', [FlipbookController::class, 'getS3PresignedUrl'])->middleware('auth');



Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
