<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use BelongsToWorkspace, HasFactory, HasTags;

    protected $fillable = [
        'workspace_id',
        'sku',
        'name',
        'category',
        'list_price',
        'currency',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'list_price' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}
