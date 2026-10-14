<?php

namespace App\Http\Controllers;

use App\Models\Credit;
use App\Models\Reward;
use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rep-facing extras: downloadable statement and a manager/team view.
 */
class RepController extends Controller
{
    /** A rep's released credits + rewards as a downloadable CSV statement. */
    public function statement(Request $request): StreamedResponse
    {
        $userId = $request->user()->id;

        $credits = Credit::released()->where('user_id', $userId)->with('transaction')->get();
        $rewards = Reward::released()->where('user_id', $userId)->get();

        return response()->streamDownload(function () use ($credits, $rewards) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Type', 'Reference', 'Description', 'Currency', 'Amount']);
            foreach ($credits as $c) {
                fputcsv($out, ['Credit', $c->transaction?->external_id ?? '', 'Credited amount', $c->currency, (float) $c->credited_amount]);
            }
            foreach ($rewards as $r) {
                fputcsv($out, ['Payout', '#'.$r->calc_run_id, $r->reward_type->label(), $r->currency, (float) $r->computed_amount]);
            }
            fclose($out);
        }, 'my-statement.csv', ['Content-Type' => 'text/csv']);
    }

    /** Managers see their direct reports' released attainment + payout. */
    public function team(Request $request): View
    {
        $managerId = $request->user()->id;
        $workspaceId = app(WorkspaceContext::class)->id();

        $reportIds = DB::table('workspace_user')
            ->where('workspace_id', $workspaceId)
            ->where('manager_id', $managerId)
            ->pluck('user_id');

        $credits = Credit::released()->whereIn('user_id', $reportIds)->with('user')->get()->groupBy('user_id');
        $payouts = Reward::released()->whereIn('user_id', $reportIds)->get()->groupBy('user_id');

        $team = $reportIds->map(fn ($id) => [
            'name' => optional($credits->get($id)?->first()?->user ?? $payouts->get($id)?->first()?->user)->name
                ?? User::find($id)?->name ?? 'Unknown',
            'credited' => round((float) ($credits->get($id)?->sum('credited_amount') ?? 0), 2),
            'payout' => round((float) ($payouts->get($id)?->sum('computed_amount') ?? 0), 2),
        ])->sortByDesc('credited')->values();

        return view('rep.team', ['team' => $team, 'hasReports' => $reportIds->isNotEmpty()]);
    }
}
