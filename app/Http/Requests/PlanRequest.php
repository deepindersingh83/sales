<?php

namespace App\Http\Requests;

use App\Enums\PlanStatus;
use App\Enums\RewardType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a plan plus its nested tiers and reward rules (create + update).
 * Authorization is handled in the controller via policies.
 */
class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'period_type' => ['required', Rule::in(['monthly', 'quarterly', 'annual', 'custom'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::enum(PlanStatus::class)],
            'performance_metric' => ['required', Rule::in(['revenue', 'profit', 'custom'])],
            'currency' => ['required', 'string', 'size:3'],

            'tiers' => ['array'],
            'tiers.*.threshold_from' => ['required', 'numeric', 'min:0'],
            'tiers.*.threshold_to' => ['nullable', 'numeric', 'gte:tiers.*.threshold_from'],
            'tiers.*.kind' => ['required', Rule::in(['rate', 'amount'])],
            'tiers.*.rate_or_amount' => ['required', 'numeric', 'min:0'],
            'tiers.*.is_cumulative' => ['boolean'],

            'reward_rules' => ['array'],
            'reward_rules.*.reward_type' => ['required', Rule::enum(RewardType::class)],
            'reward_rules.*.value' => ['nullable', 'numeric'],
            'reward_rules.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalise checkbox-style booleans on tier rows.
        $tiers = $this->input('tiers', []);
        foreach ($tiers as $i => $tier) {
            $tiers[$i]['is_cumulative'] = filter_var(
                $tier['is_cumulative'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );
        }
        $this->merge(['tiers' => $tiers]);
    }
}
