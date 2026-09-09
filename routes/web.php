<?php

use App\Http\Controllers\Admin\AliasController;
use App\Http\Controllers\Admin\CalcRunController;
use App\Http\Controllers\Admin\CalcRunReleaseController;
use App\Http\Controllers\Admin\ConnectorController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\DisputeQueueController;
use App\Http\Controllers\Admin\FxRateController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\PlanAccessController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\TransactionImportController;
use App\Http\Controllers\Admin\WorkspaceSettingsController;
use App\Http\Controllers\Admin\ContestController;
use App\Http\Controllers\Admin\ImportSourceController;
use App\Http\Controllers\Admin\MemberImportController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RepController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SurveyController;
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
    Route::post('/disputes/claim', [DisputeController::class, 'claim'])->name('disputes.claim');

    // Rep extras: statement download + manager/team view.
    Route::get('/my/statement', [RepController::class, 'statement'])->name('my.statement');
    Route::get('/my/team', [RepController::class, 'team'])->name('my.team');

    // Global search across the current workspace.
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');

    // Leaderboard (everyone) + survey responses (participants).
    Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');
    Route::post('/surveys/{survey}/respond', [SurveyController::class, 'respond'])->name('surveys.respond');

    // Plan enrollment with typed-name e-signature.
    Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
    Route::post('/enrollments/{plan}/sign', [EnrollmentController::class, 'sign'])->name('enrollments.sign');

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
        Route::get('members/import', [MemberImportController::class, 'create'])->name('members.import.create');
        Route::post('members/import', [MemberImportController::class, 'store'])->name('members.import.store');
        Route::post('members', [MemberController::class, 'store'])->name('members.store');
        Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('members/{member}', [MemberController::class, 'destroy'])->name('members.destroy');

        // Product catalogue.
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        // Transactions + CSV import.
        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::post('transactions/{transaction}/exclude', [TransactionController::class, 'toggleExclude'])->name('transactions.exclude');
        Route::post('transactions/{transaction}/paid', [TransactionController::class, 'togglePaid'])->name('transactions.paid');
        Route::get('imports', [TransactionImportController::class, 'create'])->name('imports.create');
        Route::post('imports/preview', [TransactionImportController::class, 'preview'])->name('imports.preview');
        Route::post('imports', [TransactionImportController::class, 'store'])->name('imports.store');

        // Recurring / scheduled import sources.
        Route::get('import-sources', [ImportSourceController::class, 'index'])->name('import-sources.index');
        Route::post('import-sources', [ImportSourceController::class, 'store'])->name('import-sources.store');
        Route::post('import-sources/{source}/run', [ImportSourceController::class, 'run'])->name('import-sources.run');
        Route::delete('import-sources/{source}', [ImportSourceController::class, 'destroy'])->name('import-sources.destroy');

        // Alias-based crediting configuration.
        Route::resource('aliases', AliasController::class)->except(['show']);

        // Calculation runs (queued).
        Route::get('calc-runs', [CalcRunController::class, 'index'])->name('calc-runs.index');
        Route::post('plans/{plan}/calc-runs', [CalcRunController::class, 'store'])->name('plans.calc-runs.store');
        Route::post('plans/{plan}/simulate', [CalcRunController::class, 'simulate'])->name('plans.simulate');
        Route::post('plans/{plan}/true-up', [CalcRunController::class, 'trueUp'])->name('plans.true-up');
        Route::get('calc-runs/{calcRun}', [CalcRunController::class, 'show'])->name('calc-runs.show');
        Route::get('calc-runs/{calcRun}/status', [CalcRunController::class, 'status'])->name('calc-runs.status');
        Route::get('calc-runs/{calcRun}/logs', [CalcRunController::class, 'logs'])->name('calc-runs.logs');
        Route::post('calc-runs/{calcRun}/approve', [CalcRunController::class, 'approve'])->name('calc-runs.approve');

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

        // Contests / leaderboards + surveys (engagement).
        Route::get('contests', [ContestController::class, 'index'])->name('contests.index');
        Route::post('contests', [ContestController::class, 'store'])->name('contests.store');
        Route::get('contests/{contest}', [ContestController::class, 'show'])->name('contests.show');
        Route::delete('contests/{contest}', [ContestController::class, 'destroy'])->name('contests.destroy');
        Route::get('surveys', [SurveyController::class, 'index'])->name('surveys.index');
        Route::post('surveys', [SurveyController::class, 'store'])->name('surveys.store');
        Route::delete('surveys/{survey}', [SurveyController::class, 'destroy'])->name('surveys.destroy');

        // Workspace settings (Full Admin).
        Route::get('settings', [WorkspaceSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [WorkspaceSettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/api-token', [WorkspaceSettingsController::class, 'regenerateToken'])->name('settings.api-token');

        // FX rates (Full Admin).
        Route::get('fx', [FxRateController::class, 'index'])->name('fx.index');
        Route::post('fx', [FxRateController::class, 'store'])->name('fx.store');
        Route::delete('fx/{fxRate}', [FxRateController::class, 'destroy'])->name('fx.destroy');

        // Built-in reports (with ?export=csv).
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/payout-by-user', [ReportController::class, 'payoutByUser'])->name('reports.payout-by-user');
        Route::get('reports/payout-by-plan', [ReportController::class, 'payoutByPlan'])->name('reports.payout-by-plan');
        Route::get('reports/crediting', [ReportController::class, 'crediting'])->name('reports.crediting');
        Route::get('reports/double-payments', [ReportController::class, 'doublePayments'])->name('reports.double-payments');
        Route::get('reports/asc606', [ReportController::class, 'asc606'])->name('reports.asc606');
        Route::get('reports/overview', [ReportController::class, 'overview'])->name('reports.overview');
        Route::get('reports/attainment', [ReportController::class, 'attainment'])->name('reports.attainment');
        Route::get('reports/liability', [ReportController::class, 'liability'])->name('reports.liability');

        // Integrations catalogue (connector framework).
        Route::get('connectors', [ConnectorController::class, 'index'])->name('connectors.index');
    });

require __DIR__.'/auth.php';
