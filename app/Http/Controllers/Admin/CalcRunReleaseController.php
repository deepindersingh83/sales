<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\CalcRun;
use App\Services\Calculation\PayoutPipeline;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin review/release screens for a calc run's credits and rewards. Enforces
 * SalesCookie's two-stage pipeline (pending -> reviewed -> released); rep-facing
 * dashboards never show anything until it is released.
 */
class CalcRunReleaseController extends Controller
{
    public function __construct(protected PayoutPipeline $pipeline) {}

    public function credits(CalcRun $calcRun): View
    {
        Gate::authorize('view', $calcRun);

        return view('admin.release.credits', [
            'run' => $calcRun,
            'credits' => $calcRun->credits()->with(['user', 'transaction'])->orderBy('user_id')->get(),
            'counts' => $this->counts($calcRun->credits()),
        ]);
    }

    public function rewards(CalcRun $calcRun): View
    {
        Gate::authorize('view', $calcRun);

        return view('admin.release.rewards', [
            'run' => $calcRun,
            'rewards' => $calcRun->rewards()->with(['user', 'plan'])->orderBy('user_id')->get(),
            'counts' => $this->counts($calcRun->rewards()),
        ]);
    }

    public function transitionCredits(Request $request, CalcRun $calcRun): RedirectResponse
    {
        return $this->transition($request, $calcRun, 'credits');
    }

    public function transitionRewards(Request $request, CalcRun $calcRun): RedirectResponse
    {
        return $this->transition($request, $calcRun, 'rewards');
    }

    protected function transition(Request $request, CalcRun $calcRun, string $type): RedirectResponse
    {
        // Releasing is a writing admin action gated by the plan the run belongs to.
        Gate::authorize('release', $calcRun);

        $data = $request->validate([
            'action' => ['required', 'in:review,release,revert'],
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
        ]);

        $relation = $type === 'credits' ? $calcRun->credits() : $calcRun->rewards();

        $query = $relation->getQuery()->clone();
        if (! empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        }

        $changed = 0;
        foreach ($query->get() as $item) {
            $target = $this->pipeline->next($item->status, $data['action']);
            if ($target !== null) {
                $item->update(['status' => $target]);
                $changed++;
            }
        }

        return redirect()
            ->route($type === 'credits' ? 'admin.calc-runs.credits.index' : 'admin.calc-runs.rewards.index', $calcRun)
            ->with('status', ucfirst($data['action'])."d {$changed} ".$type.'.');
    }

    /**
     * @return array<string, int>
     */
    protected function counts(HasMany $relation): array
    {
        return [
            PayoutStatus::Pending->value => (clone $relation)->where('status', PayoutStatus::Pending)->count(),
            PayoutStatus::Reviewed->value => (clone $relation)->where('status', PayoutStatus::Reviewed)->count(),
            PayoutStatus::Released->value => (clone $relation)->where('status', PayoutStatus::Released)->count(),
        ];
    }
}
