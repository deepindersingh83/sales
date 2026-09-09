<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One logged step of a calculation against a transaction — the audit trail
 * that makes credits traceable and disputes resolvable.
 */
class CalcLog extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'calc_run_id',
        'transaction_id',
        'user_id',
        'step',
        'description',
        'amount_before',
        'amount_after',
        'context',
    ];

    protected function casts(): array
    {
        return [
            'amount_before' => 'decimal:6',
            'amount_after' => 'decimal:6',
            'context' => 'array',
        ];
    }

    public function calcRun(): BelongsTo
    {
        return $this->belongsTo(CalcRun::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
