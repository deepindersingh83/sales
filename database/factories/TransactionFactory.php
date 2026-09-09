<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 10000);

        return [
            'workspace_id' => Workspace::factory(),
            'import_source_id' => null,
            'external_id' => fake()->unique()->uuid(),
            'source_system' => 'csv',
            'raw_data' => ['rep' => fake()->name(), 'product' => fake()->word()],
            'amount' => $amount,
            'profit_amount' => round($amount * 0.4, 2),
            'currency' => 'USD',
            'transaction_date' => now()->toDateString(),
        ];
    }
}
