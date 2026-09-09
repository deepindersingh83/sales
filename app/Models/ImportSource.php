<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ImportSource extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'name',
        'type',
        'schedule',
        'source_path',
        'config',
        'last_synced_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'last_synced_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    /** Compute the next run timestamp for this source's cadence, from a base time. */
    public function computeNextRunAt(?\DateTimeInterface $from = null): ?Carbon
    {
        $base = $from ? Carbon::instance($from) : now();

        return match ($this->schedule) {
            'hourly' => $base->copy()->addHour(),
            'daily' => $base->copy()->addDay(),
            'weekly' => $base->copy()->addWeek(),
            default => null,
        };
    }

    /** Is this source due to run at the given moment? */
    public function isDue(?\DateTimeInterface $at = null): bool
    {
        if (! in_array($this->schedule, ['hourly', 'daily', 'weekly'], true) || ! $this->source_path) {
            return false;
        }

        $now = $at ? Carbon::instance($at) : now();

        return $this->next_run_at === null || $this->next_run_at->lte($now);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
