<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer synced from Xero contacts. Assigning an owner rep creates a
 * contact_id alias, so every invoice for the customer is credited to that rep.
 */
class Customer extends Model
{
    use BelongsToWorkspace, HasTags;

    protected $fillable = [
        'workspace_id',
        'import_source_id',
        'source_system',
        'external_id',
        'name',
        'email',
        'phone',
        'status',
        'owner_user_id',
        'alias_id',
        'raw_data',
    ];

    protected function casts(): array
    {
        return ['raw_data' => 'array'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function alias(): BelongsTo
    {
        return $this->belongsTo(Alias::class);
    }

    /** This customer's transactions (Xero invoices and credit notes carry the contact id). */
    public function transactionsQuery(): Builder
    {
        return Transaction::query()->where('raw_data->contact_id', $this->external_id);
    }
}
