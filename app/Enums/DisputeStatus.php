<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case Open = 'open';
    case Investigating = 'investigating';
    case Resolved = 'resolved';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
