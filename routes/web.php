<?php

use App\Http\Controllers\Admin\AliasController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\TransactionImportController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin area — any workspace role except Participant. Per-record authorization
// is enforced by policies inside each controller action.
Route::middleware(['auth', 'workspace.admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('plans', PlanController::class);

        // Transactions + CSV import.
        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('imports', [TransactionImportController::class, 'create'])->name('imports.create');
        Route::post('imports/preview', [TransactionImportController::class, 'preview'])->name('imports.preview');
        Route::post('imports', [TransactionImportController::class, 'store'])->name('imports.store');

        // Alias-based crediting configuration.
        Route::resource('aliases', AliasController::class)->except(['show']);
    });

require __DIR__.'/auth.php';
