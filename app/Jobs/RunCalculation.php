<?php

namespace App\Jobs;

use App\Enums\CalcRunStatus;
use App\Models\CalcRun;
use App\Services\Calculation\CalculationEngine;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs a calculation off the request cycle. The UI polls calc_runs.status.
 *
 * Queued jobs have no HTTP middleware, so we set the workspace context
 * explicitly from the run's own workspace before touching any tenant-scoped
 * model.
 */
class RunCalculation implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public int $calcRunId) {}

    public function handle(CalculationEngine $engine, WorkspaceContext $context): void
    {
        // Load the run without tenant scope (context not set yet), then pin the
        // context to its workspace for the remainder of the job.
        $run = CalcRun::withoutGlobalScopes()->findOrFail($this->calcRunId);

        $context->set($run->workspace_id);

        $run->update([
            'status' => CalcRunStatus::Running,
            'started_at' => now(),
            'error' => null,
        ]);

        try {
            $engine->run($run);

            $run->update([
                'status' => CalcRunStatus::Completed,
                'completed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $run->update([
                'status' => CalcRunStatus::Failed,
                'completed_at' => now(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        CalcRun::withoutGlobalScopes()->where('id', $this->calcRunId)->update([
            'status' => CalcRunStatus::Failed->value,
            'error' => $e->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
