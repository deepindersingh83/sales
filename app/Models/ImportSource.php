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
        'credentials',
        'last_synced_at',
        'last_error',
        'next_run_at',
    ];

    /** OAuth tokens must never be rendered or serialised. */
    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'credentials' => 'encrypted:array',
            'last_synced_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    /** Is this source an API connection (vs. a stored CSV file)? */
    public function isConnector(): bool
    {
        return $this->type === 'xero';
    }

    /** Can the runner execute this source (a stored file or a live connection)? */
    public function isRunnable(): bool
    {
        return $this->isConnector() ? ! empty($this->credentials) : (bool) $this->source_path;
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
        if (! in_array($this->schedule, ['hourly', 'daily', 'weekly'], true) || ! $this->isRunnable()) {
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
