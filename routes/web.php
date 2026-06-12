<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FilePermissionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AdminController;

Route::middleware(['auth'])->group(function () {
    Route::get('/users', [AdminController::class, 'index'])->name('admin.users.index');
    Route::get('/users/{user}/edit', [AdminController::class, 'edit'])->name('admin.users.edit');
    Route::post('/users/{user}/update-group', [AdminController::class, 'updateGroup'])->name('admin.users.updateGroup');
    Route::patch('/admin/users/{user}', [AdminController::class, 'update'])->name('admin.users.update');

    Route::get('/profile', [UserController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [UserController::class, 'update'])->name('profile.update');
    Route::get('/', [FileController::class, 'index'])->name('files.index');
    Route::post('/upload', [FileController::class, 'upload'])->name('files.upload');
    Route::get('/download/{file}', [FileController::class, 'download'])->name('files.download');
    Route::get('/raw/{file}', [FileController::class, 'raw'])->name('files.raw');
    Route::get('/thumbnail/{file}', [FileController::class, 'thumbnail'])->name('files.thumbnail');
    Route::delete('/delete/{file}', [FileController::class, 'destroy'])->name('files.delete');
    Route::patch('/files/{file}/rename', [FileController::class, 'rename'])->name('files.rename');
    Route::post('/files/{file}/move', [FileController::class, 'move'])->name('files.move');
    Route::post('/files/{file}/copy', [FileController::class, 'copy'])->name('files.copy');
    Route::put('/files/{file}/tags', [FileController::class, 'syncTags'])->name('files.tags');
    Route::get('/files/{file}/permissions', [FilePermissionController::class, 'index'])->name('files.permissions.index');
    Route::post('/files/{file}/permissions', [FilePermissionController::class, 'store'])->name('files.permissions.store');
    Route::delete('/files/{file}/permissions/{permission}', [FilePermissionController::class, 'destroy'])->name('files.permissions.destroy');
    Route::get('/files/{file}/versions/{version}/download', [FileController::class, 'downloadVersion'])->name('files.versions.download');
    Route::post('/files/{file}/versions/{version}/restore', [FileController::class, 'restoreVersion'])->name('files.versions.restore');

    Route::post('/folders', [FileController::class, 'createFolder'])->name('folders.create');
    Route::get('/folders/{folder}', [FileController::class, 'viewFolder'])->name('folders.view');
});

Auth::routes(['verify' => false]);

// Legacy dashboard route now lands on the file manager.
Route::get('/home', fn () => redirect()->route('files.index'))->name('home');
