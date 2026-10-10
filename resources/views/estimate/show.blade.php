@php
    $money = fn ($amount) => number_format((float) $amount, 2);
    $tierLabel = function (?array $tier) use ($money): string {
        if ($tier === null) {
            return 'Below the first tier';
        }

        $band = $money($tier['from']).($tier['to'] !== null ? ' – '.$money($tier['to']) : '+');
        $pays = $tier['kind'] === 'amount'
            ? 'bonus '.$money($tier['rate_or_amount'])
            : rtrim(rtrim(number_format($tier['rate_or_amount'] * 100, 4), '0'), '.').'%';

        return "Tier {$tier['number']} ({$band}, {$pays})";
    };
    $isSelf = $member->is(auth()->user());
    $requestedPlan = old('plan_id', request('plan_id'));
    $selectedPlanId = $plan ? (string) $plan->id : (is_scalar($requestedPlan) ? (string) $requestedPlan : '');
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="$isSelf ? 'Estimate my commission' : 'Estimate commission for '.$member->name"
                          subtitle="See what an extra deal would add to your commission this period" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl space-y-6">
        <x-ui.card>
            @if ($plans->isEmpty())
                <p class="text-sm text-slate-500 {{ $members ? 'mb-4' : '' }}">{{ $isSelf ? 'You are' : $member->name.' is' }} not on any active plan yet, so there is nothing to estimate.</p>
            @endif
            @if ($plans->isNotEmpty() || $members)
                <form method="GET" action="{{ route('estimate.show') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                    @if ($members)
                        <div>
                            <x-input-label for="member_id" value="Member" />
                            <select id="member_id" name="member_id" class="mt-1 block w-full border-slate-300 rounded-lg shadow-sm text-sm" onchange="this.form.plan_id.value=''; this.form.submit()">
                                @foreach ($members as $m)
                                    <option value="{{ $m->id }}" @selected($m->id === $member->id)>{{ $m->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="{{ $members ? '' : 'sm:col-span-2' }}">
                        <x-input-label for="plan_id" value="Plan" />
                        <select id="plan_id" name="plan_id" class="mt-1 block w-full border-slate-300 rounded-lg shadow-sm text-sm">
                            <option value="">Choose a plan…</option>
                            @foreach ($plans as $p)
                                <option value="{{ $p->id }}" @selected($selectedPlanId === (string) $p->id)>{{ $p->name }} ({{ $p->currency }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('plan_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="amount" value="Deal amount" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="is_scalar(old('amount', request('amount'))) ? old('amount', request('amount')) : ''" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-1" />
                    </div>
                    <x-ui.button>Estimate</x-ui.button>
                </form>
                <p class="mt-3 text-xs text-slate-400">Enter the deal in the plan's currency and performance metric (revenue or profit). Based on released credits only; reward rules and manager overrides are not included.</p>
            @endif
        </x-ui.card>

        @if ($estimate)
            @php [$periodStart, $periodEnd] = $estimate['period']; @endphp
            <div class="text-sm text-slate-500">
                {{ $plan->name }} ·
                @if ($periodStart)
                    {{ $periodStart->format('j M Y') }} – {{ $periodEnd->format('j M Y') }}
                @else
                    all released credits on this plan
                @endif
                @if ($estimate['quota']) · quota {{ $estimate['currency'] }} {{ $money($estimate['quota']) }} @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-ui.stat label="Commission now" :value="$estimate['currency'].' '.$money($estimate['current']['commission'])"
                           :hint="'Attainment '.$money($estimate['current']['attainment']).($estimate['current']['attainment_pct'] !== null ? ' · '.$estimate['current']['attainment_pct'].'% of quota' : '')" />
                <x-ui.stat label="With this deal" :value="$estimate['currency'].' '.$money($estimate['with_deal']['commission'])"
                           :hint="'Attainment '.$money($estimate['with_deal']['attainment']).($estimate['with_deal']['attainment_pct'] !== null ? ' · '.$estimate['with_deal']['attainment_pct'].'% of quota' : '')" />
                <x-ui.stat label="Extra commission" :value="$estimate['currency'].' '.$money($estimate['difference'])" accent="green" />
            </div>

            <x-ui.card>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-slate-500">Tier now</dt>
                        <dd class="font-medium text-slate-800">{{ $tierLabel($estimate['current']['tier']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Tier with this deal</dt>
                        <dd class="font-medium text-slate-800">
                            {{ $tierLabel($estimate['with_deal']['tier']) }}
                            @if ($estimate['with_deal']['tier'] !== $estimate['current']['tier'])
                                <x-ui.badge color="green" class="ml-1">New tier</x-ui.badge>
                            @endif
                        </dd>
                    </div>
                </dl>
                @if ($estimate['with_deal']['capped'])
                    <p class="mt-4 text-sm text-amber-700">This plan caps payouts, and the estimate with this deal has reached the cap.</p>
                @endif
            </x-ui.card>
        @endif
    </div>
</x-app-layout>
