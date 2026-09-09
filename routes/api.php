<?php

use App\Http\Controllers\Api\PayoutFeedController;
use App\Http\Controllers\Api\TransactionIngestController;
use Illuminate\Support\Facades\Route;

/*
 * Public REST API, authenticated by a per-workspace bearer token
 * (Authorization: Bearer wsk_...). Every route is tenant-scoped by the token.
 */
Route::middleware('auth.api')->prefix('v1')->group(function () {
    // Ingest / webhook target.
    Route::post('transactions', [TransactionIngestController::class, 'store'])->name('api.transactions.ingest');

    // BI / OData-style payouts feed (released rewards).
    Route::get('payouts', [PayoutFeedController::class, 'index'])->name('api.payouts.index');
});
