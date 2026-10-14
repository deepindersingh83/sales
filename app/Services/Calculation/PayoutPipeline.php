<?php

namespace App\Services\Calculation;

use App\Enums\PayoutStatus;

/**
 * The two-stage release pipeline shared by credits and rewards:
 * pending -> reviewed -> released (with a one-step revert). Nothing is visible
 * to reps until it reaches "released".
 */
class PayoutPipeline
{
    /**
     * Resolve the target status for an action, or null if the action is not
     * valid from the current status.
     */
    public function next(PayoutStatus $current, string $action): ?PayoutStatus
    {
        return match ($action) {
            'review' => $current === PayoutStatus::Pending ? PayoutStatus::Reviewed : null,
            'release' => $current === PayoutStatus::Reviewed ? PayoutStatus::Released : null,
            'revert' => match ($current) {
                PayoutStatus::Released => PayoutStatus::Reviewed,
                PayoutStatus::Reviewed => PayoutStatus::Pending,
                default => null,
            },
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['review', 'release', 'revert'];
    }
}
