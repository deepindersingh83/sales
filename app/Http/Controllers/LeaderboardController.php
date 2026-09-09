<?php

namespace App\Http\Controllers;

use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Overall rep leaderboard — visible to everyone in the workspace.
 */
class LeaderboardController extends Controller
{
    public function index(Request $request, LeaderboardService $leaderboard): View
    {
        return view('leaderboard.index', [
            'standings' => $leaderboard->standings(),
            'me' => $request->user()->name,
        ]);
    }
}
