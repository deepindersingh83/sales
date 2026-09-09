<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SimulateCalc;
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

    /** Trigger a true-up run — pays the delta vs already-released commission. */
    public function trueUp(Plan $plan, StartCalcRun $starter): RedirectResponse
    {
        Gate::authorize('createForPlan', [CalcRun::class, $plan]);

        $run = $starter->handle($plan, request()->user()->id, false, 'true_up');

        return redirect()
            ->route('admin.calc-runs.show', $run)
            ->with('status', 'True-up queued — it pays only the delta vs already-released commission.');
    }

    /** Projected payouts without persisting anything (what-if). */
    public function simulate(Plan $plan, SimulateCalc $simulator): View
    {
        Gate::authorize('createForPlan', [CalcRun::class, $plan]);

        $result = $simulator->handle($plan, request()->user()->id);

        return view('admin.calc_runs.simulation', [
            'plan' => $plan,
            'summary' => $result['summary'],
            'rewards' => $result['rewards'],
        ]);
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

    /** Full audit trail for a run — every rule application, filterable. */
    public function logs(CalcRun $calcRun): View
    {
        Gate::authorize('view', $calcRun);

        $logs = $calcRun->logs()
            ->with(['transaction', 'user'])
            ->when(request('step'), fn ($q, $step) => $q->where('step', $step))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $steps = $calcRun->logs()->select('step')->distinct()->pluck('step');

        return view('admin.calc_runs.logs', [
            'run' => $calcRun,
            'logs' => $logs,
            'steps' => $steps,
            'activeStep' => request('step'),
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
