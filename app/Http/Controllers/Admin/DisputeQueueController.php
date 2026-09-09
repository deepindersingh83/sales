<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin triage queue for disputes across the workspace. Rows are filtered to
 * those the acting admin may view (Plan Admins only see disputes tied to plans
 * they administer, or unlinked ones).
 */
class DisputeQueueController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Dispute::class);

        $disputes = Dispute::with(['user', 'plan'])
            ->latest()
            ->get()
            ->filter(fn (Dispute $d) => $request->user()->can('view', $d))
            ->values();

        return view('admin.disputes.index', ['disputes' => $disputes]);
    }
}
