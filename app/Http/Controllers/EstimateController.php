<?php

namespace App\Http\Controllers;

use App\Enums\PlanStatus;
use App\Models\CalcRun;
use App\Models\Credit;
use App\Models\Enrollment;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Calculation\CommissionEstimator;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Estimate my commission": a rep picks one of their active plans and a
 * hypothetical deal amount and sees the commission it would add. Admins may
 * estimate for any member (?member_id=), on plans they may view.
 */
class EstimateController extends Controller
{
    public function show(Request $request, CommissionEstimator $estimator, WorkspaceContext $context): View
    {
        $viewer = $request->user();
        $isAdmin = $viewer->currentRole()?->isAdmin() ?? false;
        $members = $isAdmin
            ? Workspace::withoutGlobalScopes()->findOrFail($context->id())->users()->orderBy('name')->get(['users.id', 'users.name'])
            : null;

        $memberId = $request->integer('member_id') ?: $viewer->id;
        if ($memberId !== $viewer->id) {
            abort_unless($isAdmin, 403);
            abort_unless($members->contains('id', $memberId), 404);
        }
        $member = $memberId === $viewer->id ? $viewer : User::findOrFail($memberId);

        $plans = $this->plansFor($member, $isAdmin && $member->isNot($viewer) ? $viewer : null);

        $data = $request->validate([
            'plan_id' => ['nullable', 'integer', Rule::in($plans->modelKeys())],
            'amount' => ['nullable', 'required_with:plan_id', 'numeric', 'min:0', 'max:1000000000000'],
        ], [
            'plan_id.in' => 'Choose one of your active plans.',
        ]);

        $plan = isset($data['plan_id']) ? $plans->find($data['plan_id']) : null;

        return view('estimate.show', [
            'member' => $member,
            'members' => $members,
            'plans' => $plans,
            'plan' => $plan,
            'amount' => $data['amount'] ?? null,
            'estimate' => $plan ? $estimator->estimate($member, $plan, (float) $data['amount']) : null,
        ]);
    }

    /**
     * Active plans the member is enrolled in or has released credits on. When
     * an admin estimates for someone else, only plans that admin may view.
     *
     * @return Collection<int, Plan>
     */
    protected function plansFor(User $member, ?User $admin): Collection
    {
        $enrolled = Enrollment::where('user_id', $member->id)->select('plan_id');
        $credited = CalcRun::query()
            ->whereIn('id', Credit::released()->where('user_id', $member->id)->select('calc_run_id'))
            ->select('plan_id');

        return Plan::query()
            ->where('status', PlanStatus::Active)
            ->where(fn ($query) => $query->whereIn('id', $enrolled)->orWhereIn('id', $credited))
            ->orderBy('name')
            ->get()
            ->filter(fn (Plan $plan): bool => $admin === null || $admin->canViewPlan($plan))
            ->values();
    }
}
