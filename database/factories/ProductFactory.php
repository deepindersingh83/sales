<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'sku' => strtoupper(Str::random(8)),
            'name' => fake()->words(3, true),
            'category' => fake()->randomElement(['Software', 'Hardware', 'Services']),
            'list_price' => fake()->randomFloat(2, 10, 5000),
            'currency' => 'USD',
            'active' => true,
        ];
    }
}
