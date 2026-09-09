<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'plan_id',
        'user_id',
        'enrolled_at',
        'signature',
        'signed_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
