<?php

namespace Tests\Feature\Calculation;

use App\Actions\StartCalcRun;
use App\Enums\PayoutStatus;
use App\Enums\RewardType;
use App\Enums\Role;
use App\Models\Alias;
use App\Models\CalcRun;
use App\Models\Credit;
use App\Models\Enrollment;
use App\Models\Plan;
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Calculation\CommissionEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CommissionEstimatorTest extends TestCase
{
    use RefreshDatabase;

    private function deal(Workspace $workspace, string $reference, float $amount, float $profit = 0.0): Transaction
    {
        return Transaction::factory()->for($workspace)->create([
            'external_id' => $reference,
            'amount' => $amount,
            'profit_amount' => $profit,
            'raw_data' => ['rep' => 'Alice'],
        ]);
    }

    /** Run the engine for the plan and release its credits; returns the rep's commission. */
    private function runAndRelease(Plan $plan, User $rep): float
    {
        $run = app(StartCalcRun::class)->handle($plan, $rep->id);
        Credit::where('calc_run_id', $run->id)->update(['status' => PayoutStatus::Released]);

        return (float) Reward::where('calc_run_id', $run->id)
            ->where('user_id', $rep->id)
            ->where('reward_type', RewardType::Commission->value)
            ->value('computed_amount');
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: array<int, array<string, mixed>>, 2: float, 3: float, 4: float}>
     */
    public static function engineScenarios(): array
    {
        return [
            'cumulative tiers crossing a band' => [
                [],
                [
                    ['threshold_from' => 0, 'threshold_to' => 10000, 'kind' => 'rate', 'rate_or_amount' => 0.05, 'is_cumulative' => true],
                    ['threshold_from' => 10000, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.08, 'is_cumulative' => true],
                ],
                6000, 0, 9000,
            ],
            'non-cumulative tier reached exactly at its boundary' => [
                [],
                [
                    ['threshold_from' => 0, 'threshold_to' => 10000, 'kind' => 'rate', 'rate_or_amount' => 0.05, 'is_cumulative' => false],
                    ['threshold_from' => 10000, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.08, 'is_cumulative' => false],
                ],
                4000, 0, 6000,
            ],
            'custom formula using attainment, quota and profit' => [
                ['commission_formula' => 'attainment * 0.05 + max(attainment - quota, 0) * 0.02 + profit * 0.1', 'quota' => 8000],
                [],
                5000, 2000, 4000,
            ],
            'payout cap' => [
                ['payout_cap' => 700],
                [['threshold_from' => 0, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true]],
                5000, 0, 5000,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $planAttributes
     * @param  array<int, array<string, mixed>>  $tiers
     */
    #[DataProvider('engineScenarios')]
    public function test_estimate_matches_what_the_engine_pays(array $planAttributes, array $tiers, float $existing, float $existingProfit, float $dealAmount): void
    {
        $workspace = Workspace::factory()->create();
        $this->useWorkspace($workspace);
        $rep = $this->makeMember($workspace, Role::Participant);
        Alias::create(['workspace_id' => $workspace->id, 'user_id' => $rep->id, 'alias_value' => 'Alice', 'match_field' => 'rep', 'match_type' => 'exact']);
        $plan = Plan::factory()->for($workspace)->active()->create($planAttributes);
        foreach ($tiers as $i => $tier) {
            $plan->tiers()->create($tier + ['sort_order' => $i]);
        }
        $this->deal($workspace, 'EXISTING', $existing, $existingProfit);
        $commissionNow = $this->runAndRelease($plan, $rep);

        $estimate = app(CommissionEstimator::class)->estimate($rep, $plan, $dealAmount);

        $this->deal($workspace, 'NEW-DEAL', $dealAmount);
        $commissionWithDeal = $this->runAndRelease($plan, $rep);

        $this->assertEqualsWithDelta($commissionNow, $estimate['current']['commission'], 0.0001);
        $this->assertEqualsWithDelta($commissionWithDeal, $estimate['with_deal']['commission'], 0.0001);
        $this->assertEqualsWithDelta($commissionWithDeal - $commissionNow, $estimate['difference'], 0.0001);
    }

    public function test_estimate_reports_quota_attainment_tier_and_cap(): void
    {
        $workspace = Workspace::factory()->create();
        $this->useWorkspace($workspace);
        $rep = $this->makeMember($workspace, Role::Participant);
        $plan = Plan::factory()->for($workspace)->active()->create(['quota' => 20000, 'payout_cap' => 900]);
        $plan->tiers()->create(['threshold_from' => 0, 'threshold_to' => 10000, 'kind' => 'rate', 'rate_or_amount' => 0.05, 'is_cumulative' => true, 'sort_order' => 0]);
        $plan->tiers()->create(['threshold_from' => 10000, 'threshold_to' => null, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true, 'sort_order' => 1]);
        $run = CalcRun::create(['workspace_id' => $workspace->id, 'plan_id' => $plan->id, 'status' => 'completed']);
        Credit::create(['workspace_id' => $workspace->id, 'calc_run_id' => $run->id, 'transaction_id' => $this->deal($workspace, 'A', 5000)->id, 'user_id' => $rep->id, 'credited_amount' => 5000, 'currency' => 'USD', 'status' => PayoutStatus::Released]);

        $estimate = app(CommissionEstimator::class)->estimate($rep, $plan, 10000);

        $this->assertSame(25.0, $estimate['current']['attainment_pct']);
        $this->assertSame(1, $estimate['current']['tier']['number']);
        $this->assertSame(75.0, $estimate['with_deal']['attainment_pct']);
        $this->assertSame(2, $estimate['with_deal']['tier']['number']);
        $this->assertSame(900.0, $estimate['with_deal']['commission']);
        $this->assertTrue($estimate['with_deal']['capped']);
    }

    public function test_only_released_credits_in_the_plan_period_count_toward_current_attainment(): void
    {
        $workspace = Workspace::factory()->create();
        $this->useWorkspace($workspace);
        $rep = $this->makeMember($workspace, Role::Participant);
        $plan = Plan::factory()->for($workspace)->active()->create();
        $plan->tiers()->create(['threshold_from' => 0, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true, 'sort_order' => 0]);
        $run = CalcRun::create(['workspace_id' => $workspace->id, 'plan_id' => $plan->id, 'status' => 'completed']);
        $credit = fn (Transaction $tx, PayoutStatus $status) => Credit::create(['workspace_id' => $workspace->id, 'calc_run_id' => $run->id, 'transaction_id' => $tx->id, 'user_id' => $rep->id, 'credited_amount' => $tx->amount, 'currency' => 'USD', 'status' => $status]);
        $credit($this->deal($workspace, 'RELEASED', 1000), PayoutStatus::Released);
        $credit($this->deal($workspace, 'PENDING', 2000), PayoutStatus::Pending);
        $credit(Transaction::factory()->for($workspace)->create(['amount' => 4000, 'transaction_date' => now()->subYear()]), PayoutStatus::Released);

        $estimate = app(CommissionEstimator::class)->estimate($rep, $plan, 0);

        $this->assertSame(1000.0, $estimate['current']['attainment']);
    }

    public function test_participant_sees_an_estimate_for_their_own_plan(): void
    {
        $workspace = Workspace::factory()->create();
        $rep = $this->actingAsMember($workspace, Role::Participant);
        $plan = Plan::factory()->for($workspace)->active()->create(['name' => 'Rep Plan']);
        $plan->tiers()->create(['threshold_from' => 0, 'kind' => 'rate', 'rate_or_amount' => 0.1, 'is_cumulative' => true, 'sort_order' => 0]);
        Enrollment::create(['workspace_id' => $workspace->id, 'plan_id' => $plan->id, 'user_id' => $rep->id, 'enrolled_at' => now()]);

        $this->get(route('estimate.show', ['plan_id' => $plan->id, 'amount' => 2500]))
            ->assertOk()
            ->assertViewHas('estimate', fn (array $estimate) => $estimate['difference'] === 250.0)
            ->assertSee('USD 250.00');
    }

    public function test_participant_only_sees_active_plans_they_are_on(): void
    {
        $workspace = Workspace::factory()->create();
        $rep = $this->actingAsMember($workspace, Role::Participant);
        $mine = Plan::factory()->for($workspace)->active()->create(['name' => 'Enrolled Plan']);
        Enrollment::create(['workspace_id' => $workspace->id, 'plan_id' => $mine->id, 'user_id' => $rep->id, 'enrolled_at' => now()]);
        $draft = Plan::factory()->for($workspace)->create(['name' => 'Draft Plan']);
        Enrollment::create(['workspace_id' => $workspace->id, 'plan_id' => $draft->id, 'user_id' => $rep->id, 'enrolled_at' => now()]);
        $other = Plan::factory()->for($workspace)->active()->create(['name' => 'Someone Elses Plan']);

        $this->get(route('estimate.show'))
            ->assertOk()
            ->assertSee('Enrolled Plan')
            ->assertDontSee('Draft Plan')
            ->assertDontSee('Someone Elses Plan');

        $this->get(route('estimate.show', ['plan_id' => $other->id, 'amount' => 100]))
            ->assertSessionHasErrors(['plan_id' => 'Choose one of your active plans.']);
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'missing' => ['', 'The amount field is required when plan id is present.'],
            'negative' => [-5, 'The amount field must be at least 0.'],
            'not a number' => ['lots', 'The amount field must be a number.'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_deal_amount_is_validated(mixed $amount, string $message): void
    {
        $workspace = Workspace::factory()->create();
        $rep = $this->actingAsMember($workspace, Role::Participant);
        $plan = Plan::factory()->for($workspace)->active()->create();
        Enrollment::create(['workspace_id' => $workspace->id, 'plan_id' => $plan->id, 'user_id' => $rep->id, 'enrolled_at' => now()]);

        $this->get(route('estimate.show', ['plan_id' => $plan->id, 'amount' => $amount]))
            ->assertSessionHasErrors(['amount' => $message]);
    }

    public function test_participant_cannot_estimate_for_another_member(): void
    {
        $workspace = Workspace::factory()->create();
        $other = $this->makeMember($workspace, Role::Participant);
        $this->actingAsMember($workspace, Role::Participant);

        $this->get(route('estimate.show', ['member_id' => $other->id]))->assertForbidden();
    }

    public function test_admin_can_estimate_for_a_member(): void
    {
        $workspace = Workspace::factory()->create();
        $rep = $this->makeMember($workspace, Role::Participant, ['name' => 'Rita Rep']);
        $this->actingAsMember($workspace, Role::FullAdmin);
        $plan = Plan::factory()->for($workspace)->active()->create();
        Enrollment::create(['workspace_id' => $workspace->id, 'plan_id' => $plan->id, 'user_id' => $rep->id, 'enrolled_at' => now()]);

        $this->get(route('estimate.show', ['member_id' => $rep->id, 'plan_id' => $plan->id, 'amount' => 100]))
            ->assertOk()
            ->assertSee('Estimate commission for Rita Rep')
            ->assertViewHas('estimate');
    }
}
