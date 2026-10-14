<?php

namespace App\Actions;

use App\Enums\CalcRunStatus;
use App\Models\CalcRun;
use App\Models\Plan;
use App\Models\Reward;
use App\Services\Calculation\CalculationEngine;
use App\Services\Calculation\PlanSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Projected ("what-if") payouts without side effects: run the full engine
 * inside a transaction that is always rolled back, capturing the results in
 * memory first. Nothing is persisted — no snapshot, run, credit, or reward.
 */
class SimulateCalc
{
    public function __construct(
        protected PlanSnapshot $snapshot,
        protected CalculationEngine $engine,
    ) {}

    /**
     * @return array{summary: array<string,mixed>, rewards: array<int, array<string,mixed>>}
     */
    public function handle(Plan $plan, ?int $userId = null): array
    {
        $summary = ['credited' => 0, 'uncredited' => 0, 'reps' => 0, 'commission_total' => 0.0];
        $rewards = [];

        DB::beginTransaction();
        try {
            $version = $this->snapshot->capture($plan, $userId);
            $run = CalcRun::create([
                'plan_id' => $plan->id,
                'plan_version_id' => $version->id,
                'status' => CalcRunStatus::Running,
                'is_simulation' => true,
                'triggered_by_user_id' => $userId,
            ]);

            $summary = $this->engine->run($run);

            $rewards = Reward::where('calc_run_id', $run->id)
                ->with('user:id,name')
                ->get()
                ->map(fn (Reward $r) => [
                    'user' => $r->user?->name ?? 'Unknown',
                    'reward_type' => $r->reward_type->value,
                    'amount' => $r->computed_amount !== null ? (float) $r->computed_amount : null,
                    'currency' => $r->currency,
                ])
                ->all();
        } finally {
            DB::rollBack(); // discard everything — this was a simulation
        }

        return ['summary' => $summary, 'rewards' => $rewards];
    }
}
