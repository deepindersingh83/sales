<?php

namespace Database\Factories;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->words(3, true).' Plan',
            'description' => fake()->sentence(),
            'period_type' => 'monthly',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'status' => PlanStatus::Draft,
            'performance_metric' => 'revenue',
            'currency' => 'USD',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => PlanStatus::Active]);
    }
}
