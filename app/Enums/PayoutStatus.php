<?php

namespace App\Enums;

/**
 * The two-stage release pipeline shared by credits and rewards:
 * pending -> reviewed -> released. Nothing rep-facing is shown until released.
 */
enum PayoutStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Released = 'released';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
