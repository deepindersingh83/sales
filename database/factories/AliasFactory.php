<?php

namespace Database\Factories;

use App\Models\Alias;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alias>
 */
class AliasFactory extends Factory
{
    protected $model = Alias::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'team_id' => null,
            'alias_value' => fake()->name(),
            'match_field' => 'rep',
            'match_type' => 'exact',
        ];
    }
}
