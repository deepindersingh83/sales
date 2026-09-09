<?php

namespace Tests\Unit;

use App\Services\Calculation\TierCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Tiered-commission math — one of the four costliest bug areas per the spec.
 * Pure, no DB.
 */
class TierCalculatorTest extends TestCase
{
    private TierCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new TierCalculator;
    }

    private function rateTier(float $from, ?float $to, float $rate, bool $cumulative): array
    {
        return ['threshold_from' => $from, 'threshold_to' => $to, 'kind' => 'rate', 'rate_or_amount' => $rate, 'is_cumulative' => $cumulative];
    }

    public function test_cumulative_progressive_math(): void
    {
        // 5% on first 10k, 8% above.
        $tiers = [
            $this->rateTier(0, 10000, 0.05, true),
            $this->rateTier(10000, null, 0.08, true),
        ];

        // 15k -> 10k*5% + 5k*8% = 500 + 400 = 900.
        $this->assertEqualsWithDelta(900.0, $this->calc->commission($tiers, 15000)['total'], 0.001);

        // 8k -> 8k*5% = 400 (second tier contributes 0).
        $this->assertEqualsWithDelta(400.0, $this->calc->commission($tiers, 8000)['total'], 0.001);

        // 0 -> 0.
        $this->assertEqualsWithDelta(0.0, $this->calc->commission($tiers, 0)['total'], 0.001);
    }

    public function test_non_cumulative_single_reached_tier_applies_to_whole_amount(): void
    {
        // Whichever band contains attainment applies its rate to the full amount.
        $tiers = [
            $this->rateTier(0, 10000, 0.05, false),
            $this->rateTier(10000, 20000, 0.08, false),
            $this->rateTier(20000, null, 0.10, false),
        ];

        // 15k is in the 10k-20k band -> 15k * 8% = 1200 (only that tier).
        $this->assertEqualsWithDelta(1200.0, $this->calc->commission($tiers, 15000)['total'], 0.001);

        // 25k is in the open-ended top band -> 25k * 10% = 2500.
        $this->assertEqualsWithDelta(2500.0, $this->calc->commission($tiers, 25000)['total'], 0.001);

        // 5k is in the first band -> 5k * 5% = 250.
        $this->assertEqualsWithDelta(250.0, $this->calc->commission($tiers, 5000)['total'], 0.001);
    }

    public function test_amount_tier_pays_flat_bonus_when_reached_and_stacks(): void
    {
        $tiers = [
            ['threshold_from' => 10000, 'threshold_to' => null, 'kind' => 'amount', 'rate_or_amount' => 500, 'is_cumulative' => false],
            ['threshold_from' => 20000, 'threshold_to' => null, 'kind' => 'amount', 'rate_or_amount' => 1000, 'is_cumulative' => false],
        ];

        $this->assertEqualsWithDelta(0.0, $this->calc->commission($tiers, 5000)['total'], 0.001);
        $this->assertEqualsWithDelta(500.0, $this->calc->commission($tiers, 15000)['total'], 0.001);
        // Both thresholds reached -> bonuses stack.
        $this->assertEqualsWithDelta(1500.0, $this->calc->commission($tiers, 25000)['total'], 0.001);
    }

    public function test_rate_and_amount_tiers_combine(): void
    {
        $tiers = [
            $this->rateTier(0, null, 0.05, true),
            ['threshold_from' => 10000, 'threshold_to' => null, 'kind' => 'amount', 'rate_or_amount' => 250, 'is_cumulative' => false],
        ];

        // 12k * 5% = 600, plus 250 bonus for reaching 10k = 850.
        $this->assertEqualsWithDelta(850.0, $this->calc->commission($tiers, 12000)['total'], 0.001);
    }

    public function test_breakdown_is_recorded_for_audit(): void
    {
        $tiers = [
            $this->rateTier(0, 10000, 0.05, true),
            $this->rateTier(10000, null, 0.08, true),
        ];

        $breakdown = $this->calc->commission($tiers, 15000)['breakdown'];
        $this->assertCount(2, $breakdown);
        $this->assertEqualsWithDelta(500.0, $breakdown[0]['contribution'], 0.001);
        $this->assertEqualsWithDelta(400.0, $breakdown[1]['contribution'], 0.001);
    }
}
