<?php

use App\Http\Controllers\Admin\AliasController;
use App\Http\Controllers\Admin\CalcRunController;
use App\Http\Controllers\Admin\CalcRunReleaseController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\DisputeQueueController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\PlanAccessController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\TransactionImportController;
use App\Http\Controllers\Admin\WorkspaceSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Disputes — participants raise and follow their own; admins triage via the
    // admin queue. Per-record access is enforced by DisputePolicy.
    Route::get('/disputes', [DisputeController::class, 'index'])->name('disputes.index');
    Route::get('/disputes/create', [DisputeController::class, 'create'])->name('disputes.create');
    Route::post('/disputes', [DisputeController::class, 'store'])->name('disputes.store');
    Route::get('/disputes/{dispute}', [DisputeController::class, 'show'])->name('disputes.show');
    Route::post('/disputes/{dispute}/comments', [DisputeController::class, 'storeComment'])->name('disputes.comments.store');
    Route::patch('/disputes/{dispute}/resolve', [DisputeController::class, 'resolve'])->name('disputes.resolve');

    // Multi-company: switch between workspaces / create a new one.
    Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])->name('workspaces.switch');
    Route::get('/workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
});

// Admin area — any workspace role except Participant. Per-record authorization
// is enforced by policies inside each controller action.
Route::middleware(['auth', 'workspace.admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('plans', PlanController::class);

        // Per-plan access management (Full Admin).
        Route::get('plans/{plan}/access', [PlanAccessController::class, 'edit'])->name('plans.access.edit');
        Route::put('plans/{plan}/access', [PlanAccessController::class, 'update'])->name('plans.access.update');

        // Team / member + role management (Full Admin).
        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        Route::post('members', [MemberController::class, 'store'])->name('members.store');
        Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

        // Transactions + CSV import.
        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('imports', [TransactionImportController::class, 'create'])->name('imports.create');
        Route::post('imports/preview', [TransactionImportController::class, 'preview'])->name('imports.preview');
        Route::post('imports', [TransactionImportController::class, 'store'])->name('imports.store');

        // Alias-based crediting configuration.
        Route::resource('aliases', AliasController::class)->except(['show']);

        // Calculation runs (queued).
        Route::get('calc-runs', [CalcRunController::class, 'index'])->name('calc-runs.index');
        Route::post('plans/{plan}/calc-runs', [CalcRunController::class, 'store'])->name('plans.calc-runs.store');
        Route::get('calc-runs/{calcRun}', [CalcRunController::class, 'show'])->name('calc-runs.show');
        Route::get('calc-runs/{calcRun}/status', [CalcRunController::class, 'status'])->name('calc-runs.status');
        Route::get('calc-runs/{calcRun}/logs', [CalcRunController::class, 'logs'])->name('calc-runs.logs');

        // Two-stage review/release pipeline for a run's credits and rewards.
        Route::get('calc-runs/{calcRun}/credits', [CalcRunReleaseController::class, 'credits'])->name('calc-runs.credits.index');
        Route::post('calc-runs/{calcRun}/credits/transition', [CalcRunReleaseController::class, 'transitionCredits'])->name('calc-runs.credits.transition');
        Route::get('calc-runs/{calcRun}/rewards', [CalcRunReleaseController::class, 'rewards'])->name('calc-runs.rewards.index');
        Route::post('calc-runs/{calcRun}/rewards/transition', [CalcRunReleaseController::class, 'transitionRewards'])->name('calc-runs.rewards.transition');
        Route::post('calc-runs/{calcRun}/adjustments', [CalcRunReleaseController::class, 'storeAdjustment'])->name('calc-runs.adjustments.store');

        // Dispute triage queue.
        Route::get('disputes', [DisputeQueueController::class, 'index'])->name('disputes.index');

        // Announcements (writers manage; reps see published ones on their dashboard).
        Route::resource('announcements', AnnouncementController::class)->except(['show']);

        // Workspace settings (Full Admin).
        Route::get('settings', [WorkspaceSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [WorkspaceSettingsController::class, 'update'])->name('settings.update');

        // Built-in reports (with ?export=csv).
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/payout-by-user', [ReportController::class, 'payoutByUser'])->name('reports.payout-by-user');
        Route::get('reports/payout-by-plan', [ReportController::class, 'payoutByPlan'])->name('reports.payout-by-plan');
        Route::get('reports/crediting', [ReportController::class, 'crediting'])->name('reports.crediting');
        Route::get('reports/double-payments', [ReportController::class, 'doublePayments'])->name('reports.double-payments');
    });

require __DIR__.'/auth.php';
