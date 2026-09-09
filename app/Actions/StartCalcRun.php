<?php

namespace App\Actions;

use App\Enums\CalcRunStatus;
use App\Jobs\RunCalculation;
use App\Models\CalcRun;
use App\Models\Plan;
use App\Services\Calculation\PlanSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Snapshots the plan, creates a queued CalcRun, and dispatches the calculation
 * job. Returns the CalcRun so the UI can poll its status.
 */
class StartCalcRun
{
    public function __construct(protected PlanSnapshot $snapshot) {}

    public function handle(Plan $plan, ?int $userId = null, bool $isSimulation = false, string $mode = 'standard'): CalcRun
    {
        return DB::transaction(function () use ($plan, $userId, $isSimulation, $mode) {
            $version = $this->snapshot->capture($plan, $userId);

            $run = CalcRun::create([
                'plan_id' => $plan->id,
                'plan_version_id' => $version->id,
                'status' => CalcRunStatus::Queued,
                'is_simulation' => $isSimulation,
                'mode' => $mode,
                'triggered_by_user_id' => $userId,
            ]);

            RunCalculation::dispatch($run->id);

            return $run;
        });
    }
}
