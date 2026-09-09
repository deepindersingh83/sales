<?php

namespace App\Http\Controllers\Admin;

use App\Actions\StartCalcRun;
use App\Http\Controllers\Controller;
use App\Models\CalcRun;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CalcRunController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', CalcRun::class);

        $runs = CalcRun::with('plan')
            ->latest()
            ->get()
            ->filter(fn (CalcRun $run) => request()->user()->can('view', $run))
            ->values();

        return view('admin.calc_runs.index', ['runs' => $runs]);
    }

    /** Trigger a calc run for a plan (queued). */
    public function store(Plan $plan, StartCalcRun $starter): RedirectResponse
    {
        Gate::authorize('createForPlan', [CalcRun::class, $plan]);

        $run = $starter->handle($plan, request()->user()->id);

        return redirect()
            ->route('admin.calc-runs.show', $run)
            ->with('status', 'Calculation queued. This page refreshes as it runs.');
    }

    public function show(CalcRun $calcRun): View
    {
        Gate::authorize('view', $calcRun);

        $calcRun->load(['plan', 'planVersion']);

        return view('admin.calc_runs.show', [
            'run' => $calcRun,
            'creditsCount' => $calcRun->credits()->count(),
            'rewardsCount' => $calcRun->rewards()->count(),
            'logsCount' => $calcRun->logs()->count(),
        ]);
    }

    /** Lightweight JSON endpoint the show page polls. */
    public function status(CalcRun $calcRun): JsonResponse
    {
        Gate::authorize('view', $calcRun);

        return response()->json([
            'status' => $calcRun->status->value,
            'is_terminal' => $calcRun->status->isTerminal(),
            'credits' => $calcRun->credits()->count(),
            'rewards' => $calcRun->rewards()->count(),
            'error' => $calcRun->error,
        ]);
    }
}
