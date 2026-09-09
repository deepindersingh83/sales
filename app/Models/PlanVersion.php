<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable snapshot of a plan's full config at calculation time.
 */
class PlanVersion extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'plan_id',
        'version_number',
        'snapshot',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'version_number' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
