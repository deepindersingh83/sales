<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanTier>
 */
class PlanTierFactory extends Factory
{
    protected $model = PlanTier::class;

    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'threshold_from' => 0,
            'threshold_to' => null,
            'kind' => 'rate',
            'rate_or_amount' => 0.05,
            'is_cumulative' => true,
            'sort_order' => 0,
        ];
    }
}
