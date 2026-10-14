<?php

namespace Database\Seeders;

use App\Enums\PlanStatus;
use App\Enums\Role;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Spins up a self-contained demo workspace with a team, a product catalogue,
 * tagged products and a batch of transactions — enough to explore every screen
 * without wiring up real data. Idempotent per email/slug.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::firstOrCreate(
            ['slug' => 'acme-demo'],
            ['name' => 'Acme Demo Co', 'base_currency' => 'USD', 'brand_color' => '#4f46e5'],
        );

        app(WorkspaceContext::class)->set($workspace);

        $admin = $this->user('Dana Admin', 'demo-admin@example.com');
        $manager = $this->user('Mia Chen', 'demo-mia@example.com');
        $reps = [
            $this->user('Rick Ford', 'demo-rick@example.com'),
            $this->user('Sam Ortiz', 'demo-sam@example.com'),
            $this->user('Priya Rao', 'demo-priya@example.com'),
        ];

        $this->attach($workspace, $admin, Role::FullAdmin);
        $this->attach($workspace, $manager, Role::PlanAdmin, salary: 130000);
        foreach ($reps as $i => $rep) {
            $this->attach($workspace, $rep, Role::Participant, managerId: $manager->id, salary: 80000 + $i * 5000);
        }

        // A live plan.
        Plan::firstOrCreate(
            ['workspace_id' => $workspace->id, 'name' => 'FY26 Field Sales Plan'],
            [
                'description' => 'Monthly revenue commission for field reps.',
                'period_type' => 'monthly',
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->endOfYear()->toDateString(),
                'status' => PlanStatus::Active,
                'performance_metric' => 'revenue',
                'currency' => 'USD',
            ],
        );

        // Product catalogue with tags.
        $catalogue = [
            ['SW-PRO', 'Pro Subscription', 'Software', 1200, ['recurring', 'saas']],
            ['SW-ENT', 'Enterprise Subscription', 'Software', 4800, ['recurring', 'saas', 'enterprise']],
            ['HW-DEV', 'Edge Device', 'Hardware', 350, ['hardware']],
            ['SV-IMP', 'Implementation Services', 'Services', 2500, ['services', 'one-time']],
            ['SV-SUP', 'Premium Support', 'Services', 900, ['services', 'recurring']],
        ];
        foreach ($catalogue as [$sku, $name, $category, $price, $tags]) {
            $product = Product::firstOrCreate(
                ['workspace_id' => $workspace->id, 'sku' => $sku],
                ['name' => $name, 'category' => $category, 'list_price' => $price, 'currency' => 'USD', 'active' => true],
            );
            $product->syncTagNames($tags);
        }

        // A batch of transactions across reps and products.
        $skus = array_column($catalogue, 0);
        for ($i = 1; $i <= 40; $i++) {
            $sku = $skus[array_rand($skus)];
            Transaction::firstOrCreate(
                ['workspace_id' => $workspace->id, 'source_system' => 'demo', 'external_id' => 'DEMO-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)],
                [
                    'amount' => random_int(500, 25000),
                    'currency' => 'USD',
                    'transaction_date' => now()->subDays(random_int(0, 120))->toDateString(),
                    'raw_data' => ['rep' => $reps[array_rand($reps)]->email, 'sku' => $sku],
                ],
            );
        }

        $this->command?->info('Demo workspace ready: acme-demo — login demo-admin@example.com / password');
    }

    protected function user(string $name, string $email): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make('password'), 'remember_token' => Str::random(10)],
        );
    }

    protected function attach(Workspace $workspace, User $user, Role $role, ?int $managerId = null, ?float $salary = null): void
    {
        if ($user->belongsToWorkspace($workspace)) {
            $workspace->users()->updateExistingPivot($user->id, ['role' => $role->value, 'manager_id' => $managerId, 'salary' => $salary]);

            return;
        }

        $workspace->users()->attach($user->id, ['role' => $role->value, 'manager_id' => $managerId, 'salary' => $salary]);
    }
}
