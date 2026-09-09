<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SavePlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Plan::class);

        // Row-level visibility filtering on top of the workspace scope.
        $plans = Plan::query()
            ->withCount(['tiers', 'enrollments'])
            ->latest()
            ->get()
            ->filter(fn (Plan $plan) => request()->user()->can('view', $plan))
            ->values();

        return view('admin.plans.index', ['plans' => $plans]);
    }

    public function create(): View
    {
        Gate::authorize('create', Plan::class);

        return view('admin.plans.form', [
            'plan' => new Plan(['status' => 'draft', 'period_type' => 'monthly', 'performance_metric' => 'revenue', 'currency' => 'USD']),
            'tiers' => [],
            'rewardRules' => [],
        ]);
    }

    public function store(PlanRequest $request, SavePlan $savePlan): RedirectResponse
    {
        Gate::authorize('create', Plan::class);

        $plan = $savePlan->handle(new Plan, $request->validated());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('status', 'Plan created.');
    }

    public function show(Plan $plan): View
    {
        Gate::authorize('view', $plan);

        $plan->load(['tiers', 'rewardRules', 'terms', 'versions']);

        return view('admin.plans.show', ['plan' => $plan]);
    }

    public function edit(Plan $plan): View
    {
        Gate::authorize('update', $plan);

        return view('admin.plans.form', [
            'plan' => $plan,
            'tiers' => $plan->tiers()->get()->toArray(),
            'rewardRules' => $plan->rewardRules()->get()->map(fn ($r) => [
                'reward_type' => $r->reward_type->value,
                'value' => $r->value,
                'label' => $r->meta['label'] ?? null,
            ])->toArray(),
        ]);
    }

    public function update(PlanRequest $request, Plan $plan, SavePlan $savePlan): RedirectResponse
    {
        Gate::authorize('update', $plan);

        $savePlan->handle($plan, $request->validated());

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('status', 'Plan updated.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        Gate::authorize('delete', $plan);

        $plan->delete();

        return redirect()
            ->route('admin.plans.index')
            ->with('status', 'Plan deleted.');
    }
}
