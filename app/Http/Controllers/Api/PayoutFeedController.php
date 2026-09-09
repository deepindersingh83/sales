<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only payouts feed for BI tools (Power BI / Tableau / OData-style
 * consumers). Returns released rewards as JSON, paginated.
 */
class PayoutFeedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rewards = Reward::released()
            ->with(['user:id,name', 'plan:id,name'])
            ->orderByDesc('id')
            ->paginate(min((int) $request->integer('per_page', 100), 500));

        return response()->json([
            'data' => $rewards->getCollection()->map(fn (Reward $r) => [
                'id' => $r->id,
                'user' => $r->user?->name,
                'plan' => $r->plan?->name,
                'reward_type' => $r->reward_type->value,
                'amount' => $r->computed_amount !== null ? (float) $r->computed_amount : null,
                'currency' => $r->currency,
                'calc_run_id' => $r->calc_run_id,
            ]),
            'meta' => [
                'current_page' => $rewards->currentPage(),
                'last_page' => $rewards->lastPage(),
                'total' => $rewards->total(),
            ],
        ]);
    }
}
