<?php

namespace App\Http\Controllers;

use App\Models\Credit;
use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The landing dashboard. Participants see only their own RELEASED credits and
 * rewards (the release gate). Admins get a light summary with links into the
 * admin area.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $role = $user->currentRole();

        if ($role === null || ! $role->isAdmin()) {
            return $this->participantDashboard($user->id);
        }

        return view('dashboard', ['role' => $role]);
    }

    protected function participantDashboard(int $userId): View
    {
        // Only released rows are ever visible to a rep.
        $credits = Credit::released()
            ->where('user_id', $userId)
            ->with(['transaction', 'calcRun.plan'])
            ->latest('id')
            ->get();

        $rewards = Reward::released()
            ->where('user_id', $userId)
            ->with('plan')
            ->latest('id')
            ->get();

        $totalCredited = (float) $credits->sum('credited_amount');
        $totalPayout = (float) $rewards->sum('computed_amount');

        return view('participant.dashboard', [
            'credits' => $credits,
            'rewards' => $rewards,
            'totalCredited' => $totalCredited,
            'totalPayout' => $totalPayout,
        ]);
    }
}
