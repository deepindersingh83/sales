<?php

namespace App\Services\Calculation;

use InvalidArgumentException;

/**
 * A safe arithmetic expression evaluator for custom commission formulas.
 *
 * Supports + - * / and parentheses, unary minus, the functions min() and max(),
 * and named variables (e.g. attainment, revenue, profit, quota, attainment_pct,
 * rate). It NEVER uses eval(): the expression is tokenised, converted to RPN
 * (shunting-yard), and evaluated, so a plan author cannot execute arbitrary code.
 */
class FormulaEvaluator
{
    /** @var array<string, int> operator precedence */
    private array $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2, 'u-' => 3];

    private const FUNCTIONS = ['min' => 2, 'max' => 2];

    /**
     * @param  array<string, float|int>  $variables
     */
    public function evaluate(string $expression, array $variables = []): float
    {
        $tokens = $this->tokenize($expression);
        $rpn = $this->toRpn($tokens);

        return $this->evalRpn($rpn, $variables);
    }

    public function isValid(string $expression, array $sampleVariables = []): bool
    {
        try {
            $this->evaluate($expression, $sampleVariables);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, array{type:string, value:string}>
     */
    private function tokenize(string $expr): array
    {
        $tokens = [];
        $len = strlen($expr);
        $i = 0;
        $prevType = null;

        while ($i < $len) {
            $ch = $expr[$i];

            if (ctype_space($ch)) {
                $i++;

                continue;
            }

            if (ctype_digit($ch) || $ch === '.') {
                $num = '';
                while ($i < $len && (ctype_digit($expr[$i]) || $expr[$i] === '.')) {
                    $num .= $expr[$i++];
                }
                $tokens[] = ['type' => 'number', 'value' => $num];
                $prevType = 'number';

                continue;
            }

            if (ctype_alpha($ch) || $ch === '_') {
                $name = '';
                while ($i < $len && (ctype_alnum($expr[$i]) || $expr[$i] === '_')) {
                    $name .= $expr[$i++];
                }
                $type = isset(self::FUNCTIONS[strtolower($name)]) ? 'function' : 'variable';
                $tokens[] = ['type' => $type, 'value' => $name];
                $prevType = $type;

                continue;
            }

            if ($ch === ',') {
                $tokens[] = ['type' => 'comma', 'value' => ','];
                $prevType = 'comma';
                $i++;

                continue;
            }

            if (in_array($ch, ['+', '-', '*', '/'], true)) {
                // Distinguish unary minus.
                if ($ch === '-' && ($prevType === null || in_array($prevType, ['operator', 'lparen', 'comma'], true))) {
                    $tokens[] = ['type' => 'operator', 'value' => 'u-'];
                } else {
                    $tokens[] = ['type' => 'operator', 'value' => $ch];
                }
                $prevType = 'operator';
                $i++;

                continue;
            }

            if ($ch === '(') {
                $tokens[] = ['type' => 'lparen', 'value' => '('];
                $prevType = 'lparen';
                $i++;

                continue;
            }

            if ($ch === ')') {
                $tokens[] = ['type' => 'rparen', 'value' => ')'];
                $prevType = 'rparen';
                $i++;

                continue;
            }

            throw new InvalidArgumentException("Unexpected character '{$ch}' in formula.");
        }

        return $tokens;
    }

    /**
     * @param  array<int, array{type:string, value:string}>  $tokens
     * @return array<int, array{type:string, value:string}>
     */
    private function toRpn(array $tokens): array
    {
        $output = [];
        $stack = [];

        foreach ($tokens as $token) {
            switch ($token['type']) {
                case 'number':
                case 'variable':
                    $output[] = $token;
                    break;
                case 'function':
                    $stack[] = $token;
                    break;
                case 'comma':
                    while ($stack && end($stack)['type'] !== 'lparen') {
                        $output[] = array_pop($stack);
                    }
                    break;
                case 'operator':
                    while ($stack && end($stack)['type'] === 'operator'
                        && $this->precedence[end($stack)['value']] >= $this->precedence[$token['value']]) {
                        $output[] = array_pop($stack);
                    }
                    $stack[] = $token;
                    break;
                case 'lparen':
                    $stack[] = $token;
                    break;
                case 'rparen':
                    while ($stack && end($stack)['type'] !== 'lparen') {
                        $output[] = array_pop($stack);
                    }
                    if (! $stack) {
                        throw new InvalidArgumentException('Mismatched parentheses.');
                    }
                    array_pop($stack); // discard lparen
                    if ($stack && end($stack)['type'] === 'function') {
                        $output[] = array_pop($stack);
                    }
                    break;
            }
        }

        while ($stack) {
            $top = array_pop($stack);
            if (in_array($top['type'], ['lparen', 'rparen'], true)) {
                throw new InvalidArgumentException('Mismatched parentheses.');
            }
            $output[] = $top;
        }

        return $output;
    }

    /**
     * @param  array<int, array{type:string, value:string}>  $rpn
     * @param  array<string, float|int>  $variables
     */
    private function evalRpn(array $rpn, array $variables): float
    {
        $stack = [];

        foreach ($rpn as $token) {
            switch ($token['type']) {
                case 'number':
                    $stack[] = (float) $token['value'];
                    break;
                case 'variable':
                    $key = $token['value'];
                    if (! array_key_exists($key, $variables)) {
                        throw new InvalidArgumentException("Unknown variable '{$key}'.");
                    }
                    $stack[] = (float) $variables[$key];
                    break;
                case 'operator':
                    if ($token['value'] === 'u-') {
                        $a = array_pop($stack);
                        $stack[] = -$a;
                        break;
                    }
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    if ($a === null || $b === null) {
                        throw new InvalidArgumentException('Malformed expression.');
                    }
                    $stack[] = match ($token['value']) {
                        '+' => $a + $b,
                        '-' => $a - $b,
                        '*' => $a * $b,
                        '/' => $b == 0.0 ? 0.0 : $a / $b,
                    };
                    break;
                case 'function':
                    $fn = strtolower($token['value']);
                    $b = array_pop($stack);
                    $a = array_pop($stack);
                    $stack[] = $fn === 'min' ? min($a, $b) : max($a, $b);
                    break;
            }
        }

        if (count($stack) !== 1) {
            throw new InvalidArgumentException('Malformed expression.');
        }

        return (float) $stack[0];
    }
}
