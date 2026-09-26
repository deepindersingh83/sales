<?php

use App\Http\Controllers\Api\ODataController;
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

/*
 * OData v4 feed for Power BI / Excel / Tableau. BI tools authenticate with
 * Basic auth (API token as password) — see AuthenticateApiToken.
 */
Route::middleware('auth.api')->prefix('odata')->group(function () {
    Route::get('/', [ODataController::class, 'service'])->name('api.odata.service');
    Route::get('$metadata', [ODataController::class, 'metadata'])->name('api.odata.metadata');
    Route::get('{entitySet}', [ODataController::class, 'entitySet'])
        ->whereIn('entitySet', ['Payouts', 'Credits', 'Transactions'])
        ->name('api.odata.entity-set');
});
