<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SimulateCalc;
use App\Actions\StartCalcRun;
use App\Enums\CalcRunStatus;
use App\Http\Controllers\Controller;
use App\Models\CalcRun;
use App\Models\Plan;
use App\Services\ActivityLogger;
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
    public function store(Plan $plan, StartCalcRun $starter, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('createForPlan', [CalcRun::class, $plan]);

        $run = $starter->handle($plan, request()->user()->id);

        $logger->log('calc_run.started', "Started calculation run #{$run->id} for plan {$plan->name}.", $run, [
            'plan_id' => $plan->id,
            'plan_version_id' => $run->plan_version_id,
        ]);

        return redirect()
            ->route('admin.calc-runs.show', $run)
            ->with('status', 'Calculation queued. This page refreshes as it runs.');
    }

    /** Approve a completed run so its rewards may be released. */
    public function approve(CalcRun $calcRun, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('release', $calcRun);

        if ($calcRun->status !== CalcRunStatus::Completed) {
            return back()->withErrors(['approval' => 'Only a completed run can be approved.']);
        }

        if ($calcRun->approved_at !== null) {
            return back()->withErrors(['approval' => 'This run is already approved.']);
        }

        $calcRun->update([
            'approved_at' => now(),
            'approved_by_user_id' => request()->user()->id,
        ]);

        $logger->log('calc_run.approved', "Approved calculation run #{$calcRun->id}.", $calcRun, [
            'plan_id' => $calcRun->plan_id,
        ]);

        return back()->with('status', 'Run approved — rewards can now be released.');
    }

    /** Trigger a true-up run — pays the delta vs already-released commission. */
    public function trueUp(Plan $plan, StartCalcRun $starter, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('createForPlan', [CalcRun::class, $plan]);

        $run = $starter->handle($plan, request()->user()->id, false, 'true_up');

        $logger->log('calc_run.true_up', "Started true-up run #{$run->id} for plan {$plan->name}.", $run, [
            'plan_id' => $plan->id,
            'plan_version_id' => $run->plan_version_id,
        ]);

        return redirect()
            ->route('admin.calc-runs.show', $run)
            ->with('status', 'True-up queued — it pays only the delta vs already-released commission.');
    }

    /** Projected payouts without persisting anything (what-if). */
    public function simulate(Plan $plan, SimulateCalc $simulator, ActivityLogger $logger): View
    {
        Gate::authorize('createForPlan', [CalcRun::class, $plan]);

        $result = $simulator->handle($plan, request()->user()->id);

        $logger->log('calc_run.simulated', "Simulated plan {$plan->name}.", $plan, [
            'commission_total' => $result['summary']['commission_total'] ?? null,
        ]);

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
