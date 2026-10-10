<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contest;
use App\Services\LeaderboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContestController extends Controller
{
    public function index(): View
    {
        return view('admin.contests.index', ['contests' => Contest::latest()->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeWrite($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'metric' => ['required', 'in:credited,payout'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'prize' => ['nullable', 'string', 'max:255'],
        ]);

        Contest::create($data);

        return redirect()->route('admin.contests.index')->with('status', 'Contest created.');
    }

    public function show(Contest $contest, LeaderboardService $leaderboard): View
    {
        return view('admin.contests.show', [
            'contest' => $contest,
            'standings' => $leaderboard->standings($contest),
        ]);
    }

    public function destroy(Request $request, Contest $contest): RedirectResponse
    {
        $this->authorizeWrite($request);

        $contest->delete();

        return redirect()->route('admin.contests.index')->with('status', 'Contest deleted.');
    }

    /** Limited Admins are read-only; only Full and Plan Admins may change data. */
    protected function authorizeWrite(Request $request): void
    {
        abort_unless($request->user()->currentRole()?->canWrite(), 403);
    }
}
