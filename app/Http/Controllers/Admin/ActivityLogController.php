<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CalcRun;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The workspace audit trail. Full Admins and Limited Admins (read-only
 * oversight) may view it; Plan Admins may not, since most entries concern the
 * whole workspace rather than their plans. Limited Admins do not see entries
 * about plans hidden from them, or those plans' calc runs.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): View|StreamedResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->currentRole(), [Role::FullAdmin, Role::LimitedAdmin], true), 403);

        $filters = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = $this->filtered($filters, $this->hiddenPlanIds($user));

        if ($request->query('export') === 'csv') {
            return $this->csv($query);
        }

        return view('admin.activity.index', [
            'logs' => $query->with('user')->paginate(50)->withQueryString(),
            'filters' => $filters,
            'members' => Workspace::withoutGlobalScopes()->findOrFail(app(WorkspaceContext::class)->id())
                ->users()->orderBy('name')->get(['users.id', 'users.name']),
        ]);
    }

    /**
     * @param  array{user_id?: int|string|null, action?: string|null, from?: string|null, to?: string|null}  $filters
     * @param  Collection<int, int>  $hiddenPlanIds
     */
    protected function filtered(array $filters, Collection $hiddenPlanIds): Builder
    {
        return ActivityLog::query()
            ->when($filters['user_id'] ?? null, fn (Builder $q, $userId) => $q->where('user_id', $userId))
            ->when($filters['action'] ?? null, fn (Builder $q, string $action) => $q->where('action', 'like', addcslashes($action, '%_\\').'%'))
            ->when($filters['from'] ?? null, fn (Builder $q, string $from) => $q->where('created_at', '>=', now()->parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $q, string $to) => $q->where('created_at', '<=', now()->parse($to)->endOfDay()))
            ->when($hiddenPlanIds->isNotEmpty(), fn (Builder $q) => $q->whereNot(fn (Builder $hidden) => $hidden
                ->where(fn (Builder $plan) => $plan
                    ->where('subject_type', (new Plan)->getMorphClass())
                    ->whereIn('subject_id', $hiddenPlanIds))
                ->orWhere(fn (Builder $run) => $run
                    ->where('subject_type', (new CalcRun)->getMorphClass())
                    ->whereIn('subject_id', CalcRun::query()->whereIn('plan_id', $hiddenPlanIds)->select('id')))))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Plans the viewer may not see (none for a Full Admin).
     *
     * @return Collection<int, int>
     */
    protected function hiddenPlanIds(User $user): Collection
    {
        if ($user->currentRole() === Role::FullAdmin) {
            return collect();
        }

        return Plan::query()->get()
            ->reject(fn (Plan $plan): bool => $user->canViewPlan($plan))
            ->pluck('id')
            ->values();
    }

    protected function csv(Builder $query): StreamedResponse
    {
        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Time', 'User', 'Action', 'Subject', 'Description', 'Details', 'IP address']);

            foreach ($query->reorder()->with('user')->lazyByIdDesc(500) as $log) {
                fputcsv($out, [
                    $log->created_at?->toDateTimeString(),
                    $this->safeCell($log->user?->name ?? 'System'),
                    $log->action,
                    $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '',
                    $this->safeCell($log->description),
                    $this->safeCell($log->properties ? json_encode($log->properties) : ''),
                    $log->ip_address,
                ]);
            }

            fclose($out);
        }, 'activity-log.csv', ['Content-Type' => 'text/csv']);
    }

    /** Neutralise spreadsheet formula injection in free-text cells. */
    protected function safeCell(?string $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
