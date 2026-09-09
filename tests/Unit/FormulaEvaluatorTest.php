<?php

namespace Tests\Unit;

use App\Services\Calculation\FormulaEvaluator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FormulaEvaluatorTest extends TestCase
{
    private FormulaEvaluator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new FormulaEvaluator;
    }

    public function test_arithmetic_and_precedence(): void
    {
        $this->assertEqualsWithDelta(14.0, $this->calc->evaluate('2 + 3 * 4'), 0.0001);
        $this->assertEqualsWithDelta(20.0, $this->calc->evaluate('(2 + 3) * 4'), 0.0001);
        $this->assertEqualsWithDelta(-6.0, $this->calc->evaluate('-2 * 3'), 0.0001);
    }

    public function test_variables_and_functions(): void
    {
        $vars = ['revenue' => 10000, 'attainment_pct' => 1.2, 'quota' => 8000];

        // 5% of revenue + 1000 bonus for being over quota.
        $this->assertEqualsWithDelta(
            10000 * 0.05 + 1000,
            $this->calc->evaluate('revenue * 0.05 + max(0, attainment_pct - 1) * 5000', $vars),
            0.01
        );

        $this->assertEqualsWithDelta(8000.0, $this->calc->evaluate('min(revenue, quota)', $vars), 0.01);
    }

    public function test_division_by_zero_is_safe(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->calc->evaluate('10 / 0'), 0.0001);
    }

    public function test_invalid_expressions_are_rejected(): void
    {
        $this->assertFalse($this->calc->isValid('revenue * '));
        $this->assertFalse($this->calc->isValid('(1 + 2'));
        $this->assertFalse($this->calc->isValid('unknown_var + 1'));

        $this->expectException(InvalidArgumentException::class);
        $this->calc->evaluate('1 ; system("rm")');
    }
}
