<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The crediting mechanism: a keyword matched against a field on incoming
 * transactions to determine who gets credited.
 */
class Alias extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $table = 'aliases';

    protected $fillable = [
        'workspace_id',
        'user_id',
        'team_id',
        'alias_value',
        'match_field',
        'match_type',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Does this alias match the given transaction?
     */
    public function matches(Transaction $transaction): bool
    {
        $field = $this->match_field;

        // Look in top-level columns first, then in the raw_data payload.
        $value = match ($field) {
            'source_system' => $transaction->source_system,
            'external_id' => $transaction->external_id,
            default => data_get($transaction->raw_data, $field),
        };

        if ($value === null) {
            return false;
        }

        $needle = mb_strtolower(trim((string) $this->alias_value));
        $haystack = mb_strtolower(trim((string) $value));

        return $this->match_type === 'contains'
            ? $needle !== '' && str_contains($haystack, $needle)
            : $haystack === $needle;
    }
}
