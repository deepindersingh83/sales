@php
    $isEdit = $plan->exists;
    $action = $isEdit ? route('admin.plans.update', $plan) : route('admin.plans.store');
    $rewardTypes = App\Enums\RewardType::cases();
    $initialTiers = old('tiers', $tiers);
    $initialRewards = old('reward_rules', $rewardRules);
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $isEdit ? 'Edit plan' : 'New plan' }}
        </h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8">
        @if ($errors->any())
            <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800">
                Please fix the errors below.
            </div>
        @endif

        <form method="POST" action="{{ $action }}"
              x-data="planBuilder(@js($initialTiers), @js($initialRewards))"
              class="space-y-6">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            {{-- Plan details --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div>
                    <x-input-label for="name" value="Plan name" />
                    <x-text-input id="name" name="name" class="block mt-1 w-full" :value="old('name', $plan->name)" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" value="Description" />
                    <textarea id="description" name="description" rows="2"
                              class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $plan->description) }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="period_type" value="Period" />
                        <select id="period_type" name="period_type" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach (['monthly','quarterly','annual','custom'] as $p)
                                <option value="{{ $p }}" @selected(old('period_type', $plan->period_type) === $p)>{{ ucfirst($p) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="performance_metric" value="Performance metric" />
                        <select id="performance_metric" name="performance_metric" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach (['revenue','profit','custom'] as $m)
                                <option value="{{ $m }}" @selected(old('performance_metric', $plan->performance_metric) === $m)>{{ ucfirst($m) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="currency" value="Currency" />
                        <x-text-input id="currency" name="currency" maxlength="3" class="block mt-1 w-full uppercase" :value="old('currency', $plan->currency ?: 'USD')" required />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="start_date" value="Start date" />
                        <x-text-input id="start_date" name="start_date" type="date" class="block mt-1 w-full" :value="old('start_date', optional($plan->start_date)->toDateString())" />
                    </div>
                    <div>
                        <x-input-label for="end_date" value="End date" />
                        <x-text-input id="end_date" name="end_date" type="date" class="block mt-1 w-full" :value="old('end_date', optional($plan->end_date)->toDateString())" />
                        <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="status" value="Status" />
                        <select id="status" name="status" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                            @foreach (App\Enums\PlanStatus::cases() as $s)
                                <option value="{{ $s->value }}" @selected(old('status', $plan->status->value ?? 'draft') === $s->value)>{{ $s->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Tiers --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Commission tiers</h3>
                    <button type="button" @click="addTier()" class="text-sm text-indigo-600 hover:text-indigo-900">+ Add tier</button>
                </div>
                <p class="text-xs text-gray-500 mb-3">
                    Cumulative tiers apply each tier's rate only to the portion of attainment within that tier
                    (progressive). Non-cumulative applies a single tier's rate to the whole amount.
                </p>

                <template x-if="tiers.length === 0">
                    <p class="text-sm text-gray-500">No tiers — a plan can still pay flat rewards below.</p>
                </template>

                <div class="space-y-3">
                    <template x-for="(tier, i) in tiers" :key="i">
                        <div class="grid grid-cols-12 gap-2 items-end border-b border-gray-100 pb-3">
                            <div class="col-span-2">
                                <label class="block text-xs text-gray-500">From</label>
                                <input type="number" step="0.01" min="0" :name="`tiers[${i}][threshold_from]`" x-model="tier.threshold_from"
                                       class="block w-full text-sm border-gray-300 rounded-md" required />
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs text-gray-500">To (blank = ∞)</label>
                                <input type="number" step="0.01" :name="`tiers[${i}][threshold_to]`" x-model="tier.threshold_to"
                                       class="block w-full text-sm border-gray-300 rounded-md" />
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs text-gray-500">Kind</label>
                                <select :name="`tiers[${i}][kind]`" x-model="tier.kind" class="block w-full text-sm border-gray-300 rounded-md">
                                    <option value="rate">Rate (%)</option>
                                    <option value="amount">Fixed amount</option>
                                </select>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-xs text-gray-500">
                                    <span x-text="tier.kind === 'rate' ? 'Rate (0.05 = 5%)' : 'Amount'"></span>
                                </label>
                                <input type="number" step="0.000001" min="0" :name="`tiers[${i}][rate_or_amount]`" x-model="tier.rate_or_amount"
                                       class="block w-full text-sm border-gray-300 rounded-md" required />
                            </div>
                            <div class="col-span-3 flex items-center gap-2">
                                <input type="hidden" :name="`tiers[${i}][is_cumulative]`" :value="tier.is_cumulative ? 1 : 0" />
                                <input type="checkbox" x-model="tier.is_cumulative" class="rounded border-gray-300 text-indigo-600" />
                                <span class="text-xs text-gray-600">Cumulative</span>
                            </div>
                            <div class="col-span-1 text-right">
                                <button type="button" @click="removeTier(i)" class="text-red-500 hover:text-red-700 text-sm">✕</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Reward rules --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Reward rules</h3>
                    <button type="button" @click="addReward()" class="text-sm text-indigo-600 hover:text-indigo-900">+ Add reward</button>
                </div>

                <div class="space-y-3">
                    <template x-for="(rule, i) in rewards" :key="i">
                        <div class="grid grid-cols-12 gap-2 items-end border-b border-gray-100 pb-3">
                            <div class="col-span-4">
                                <label class="block text-xs text-gray-500">Reward type</label>
                                <select :name="`reward_rules[${i}][reward_type]`" x-model="rule.reward_type" class="block w-full text-sm border-gray-300 rounded-md">
                                    @foreach ($rewardTypes as $rt)
                                        <option value="{{ $rt->value }}">{{ $rt->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-3">
                                <label class="block text-xs text-gray-500">Value</label>
                                <input type="number" step="0.000001" :name="`reward_rules[${i}][value]`" x-model="rule.value"
                                       class="block w-full text-sm border-gray-300 rounded-md" />
                            </div>
                            <div class="col-span-4">
                                <label class="block text-xs text-gray-500">Label (badge/prize)</label>
                                <input type="text" :name="`reward_rules[${i}][label]`" x-model="rule.label"
                                       class="block w-full text-sm border-gray-300 rounded-md" />
                            </div>
                            <div class="col-span-1 text-right">
                                <button type="button" @click="removeReward(i)" class="text-red-500 hover:text-red-700 text-sm">✕</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.plans.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create plan' }}</x-primary-button>
            </div>
        </form>
    </div>

    <script>
        function planBuilder(initialTiers, initialRewards) {
            return {
                tiers: (initialTiers || []).map(t => ({
                    threshold_from: t.threshold_from ?? 0,
                    threshold_to: t.threshold_to ?? '',
                    kind: t.kind ?? 'rate',
                    rate_or_amount: t.rate_or_amount ?? 0,
                    is_cumulative: t.is_cumulative === undefined ? true : !!Number(t.is_cumulative) || t.is_cumulative === true,
                })),
                rewards: (initialRewards || []).map(r => ({
                    reward_type: r.reward_type ?? 'cash_fixed',
                    value: r.value ?? '',
                    label: r.label ?? '',
                })),
                addTier() {
                    this.tiers.push({ threshold_from: 0, threshold_to: '', kind: 'rate', rate_or_amount: 0, is_cumulative: true });
                },
                removeTier(i) { this.tiers.splice(i, 1); },
                addReward() {
                    this.rewards.push({ reward_type: 'cash_fixed', value: '', label: '' });
                },
                removeReward(i) { this.rewards.splice(i, 1); },
            };
        }
    </script>
</x-app-layout>
