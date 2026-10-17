<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => null,
            'action' => 'plan.updated',
            'description' => fake()->sentence(),
            'properties' => null,
            'ip_address' => null,
        ];
    }
}
