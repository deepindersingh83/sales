<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'title',
        'body',
        'audience',
        'audience_user_ids',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'audience_user_ids' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
