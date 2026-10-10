<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SavePlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlanRequest;
use App\Models\Plan;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
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

    public function store(PlanRequest $request, SavePlan $savePlan, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('create', Plan::class);

        $plan = $savePlan->handle(new Plan, $request->validated());

        $logger->log('plan.created', "Created plan {$plan->name}.", $plan, [
            'attributes' => $this->auditState($plan),
        ]);

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

    public function update(PlanRequest $request, Plan $plan, SavePlan $savePlan, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('update', $plan);

        $before = $this->auditState($plan);
        $savePlan->handle($plan, $request->validated());

        $logger->log('plan.updated', "Updated plan {$plan->name}.", $plan, [
            'changes' => ActivityLogger::changes($before, $this->auditState($plan)),
        ]);

        return redirect()
            ->route('admin.plans.show', $plan)
            ->with('status', 'Plan updated.');
    }

    public function destroy(Plan $plan, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('delete', $plan);

        $plan->delete();

        $logger->log('plan.deleted', "Deleted plan {$plan->name}.", $plan);

        return redirect()
            ->route('admin.plans.index')
            ->with('status', 'Plan deleted.');
    }

    /**
     * The plan's configuration as recorded in the activity log: its own
     * attributes plus tiers and reward rules, which SavePlan replaces wholesale.
     *
     * @return array<string, mixed>
     */
    protected function auditState(Plan $plan): array
    {
        return array_merge(
            Arr::except($plan->attributesToArray(), ['id', 'workspace_id', 'created_at', 'updated_at']),
            [
                'tiers' => $plan->tiers()->get(['threshold_from', 'threshold_to', 'kind', 'rate_or_amount', 'is_cumulative'])->toArray(),
                'reward_rules' => $plan->rewardRules()->get(['reward_type', 'value', 'meta'])->toArray(),
            ],
        );
    }
}
