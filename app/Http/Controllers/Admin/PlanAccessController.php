<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Full-Admin management of per-plan access: which Plan Admins may control a
 * plan, and which Limited Admins it is hidden from.
 */
class PlanAccessController extends Controller
{
    public function edit(Plan $plan): View
    {
        Gate::authorize('manageAccess', $plan);

        $members = $this->workspace()->users()->orderBy('name')->get();

        return view('admin.plans.access', [
            'plan' => $plan,
            'planAdmins' => $members->filter(fn ($m) => $m->pivot->role === Role::PlanAdmin->value)->values(),
            'limitedAdmins' => $members->filter(fn ($m) => $m->pivot->role === Role::LimitedAdmin->value)->values(),
            'assignedIds' => $plan->assignedAdmins()->pluck('users.id')->all(),
            'hiddenIds' => $plan->hiddenFromUsers()->pluck('users.id')->all(),
        ]);
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        Gate::authorize('manageAccess', $plan);

        $data = $request->validate([
            'plan_admin_ids' => ['array'],
            'plan_admin_ids.*' => ['integer'],
            'hidden_user_ids' => ['array'],
            'hidden_user_ids.*' => ['integer'],
        ]);

        // Constrain to actual members with the right role (defence in depth).
        $members = $this->workspace()->users()->get();
        $planAdminIds = $members->filter(fn ($m) => $m->pivot->role === Role::PlanAdmin->value)->pluck('id');
        $limitedIds = $members->filter(fn ($m) => $m->pivot->role === Role::LimitedAdmin->value)->pluck('id');

        $plan->assignedAdmins()->sync($planAdminIds->intersect($data['plan_admin_ids'] ?? [])->all());
        $plan->hiddenFromUsers()->sync($limitedIds->intersect($data['hidden_user_ids'] ?? [])->all());

        return redirect()->route('admin.plans.show', $plan)->with('status', 'Plan access updated.');
    }

    protected function workspace(): Workspace
    {
        return Workspace::withoutGlobalScopes()->findOrFail(app(WorkspaceContext::class)->id());
    }
}
