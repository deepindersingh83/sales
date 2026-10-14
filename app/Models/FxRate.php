<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FX rate. workspace_id is nullable: null rows are shared/global rates, and a
 * workspace may override with its own. Single-currency in MVP; real use in
 * product Phase 2. Not globally scoped so global rates remain readable.
 */
class FxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'base_currency',
        'quote_currency',
        'rate',
        'effective_date',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:10',
            'effective_date' => 'date',
        ];
    }
}
