<?php

namespace Tests\Unit;

use App\Models\Alias;
use App\Models\Transaction;
use PHPUnit\Framework\TestCase;

/**
 * Crediting-engine matching logic — one of the four costliest bug areas per
 * the spec. Pure unit test (no DB): Alias::matches() against a Transaction.
 */
class AliasMatchingTest extends TestCase
{
    private function transaction(array $raw, array $attrs = []): Transaction
    {
        $tx = new Transaction($attrs);
        $tx->raw_data = $raw;

        return $tx;
    }

    public function test_exact_match_is_case_insensitive_and_trimmed(): void
    {
        $alias = new Alias(['alias_value' => 'Alice', 'match_field' => 'rep', 'match_type' => 'exact']);

        $this->assertTrue($alias->matches($this->transaction(['rep' => 'alice'])));
        $this->assertTrue($alias->matches($this->transaction(['rep' => '  Alice '])));
        $this->assertFalse($alias->matches($this->transaction(['rep' => 'Alicia'])));
        $this->assertFalse($alias->matches($this->transaction(['rep' => 'Bob'])));
    }

    public function test_contains_match(): void
    {
        $alias = new Alias(['alias_value' => 'north', 'match_field' => 'region', 'match_type' => 'contains']);

        $this->assertTrue($alias->matches($this->transaction(['region' => 'North East'])));
        $this->assertFalse($alias->matches($this->transaction(['region' => 'South'])));
    }

    public function test_missing_field_never_matches(): void
    {
        $alias = new Alias(['alias_value' => 'Alice', 'match_field' => 'rep', 'match_type' => 'exact']);

        $this->assertFalse($alias->matches($this->transaction(['owner' => 'Alice'])));
    }

    public function test_can_match_top_level_source_system(): void
    {
        $alias = new Alias(['alias_value' => 'salesforce', 'match_field' => 'source_system', 'match_type' => 'exact']);
        $tx = $this->transaction([], ['source_system' => 'salesforce']);

        $this->assertTrue($alias->matches($tx));
    }
}
