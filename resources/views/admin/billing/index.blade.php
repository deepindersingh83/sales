<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Billing &amp; subscription" subtitle="Your plan, usage and payee metering" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-5xl space-y-6">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        @unless ($stripeEnabled)
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                Stripe is not configured — tier changes apply immediately in self-serve mode and no card is charged. Set <code>STRIPE_SECRET</code> to enable real invoicing.
            </div>
        @endunless

        {{-- Current usage --}}
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <x-ui.stat label="Current tier" :value="$workspace->tierConfig()['name']" />
            <x-ui.stat label="Active payees (this month)" :value="$usage['active_payees']" />
            <x-ui.stat label="Billable payees" :value="$usage['billable']" />
            <x-ui.stat label="Estimated charge" :value="$usage['currency'].' '.number_format($usage['charge'], 2)" accent="green" />
        </div>

        @if ($workspace->onTrial())
            <div class="rounded-lg bg-brand-50 border border-brand-100 p-4 text-sm text-brand-800">
                Trial active — ends {{ $workspace->trial_ends_at->diffForHumans() }} ({{ $workspace->trial_ends_at->toFormattedDateString() }}). Payee limits are lifted during your trial.
            </div>
        @elseif ($usage['limit'] !== null)
            <div class="rounded-lg bg-slate-50 border border-slate-200 p-4 text-sm text-slate-600 flex items-center justify-between">
                <span>Your Free plan is limited to {{ $usage['limit'] }} members. @if ($usage['over_limit'])<span class="text-rose-600 font-medium">You are at the limit.</span>@endif</span>
                <form method="POST" action="{{ route('admin.billing.trial') }}">
                    @csrf
                    <x-ui.button variant="secondary">Start {{ config('billing.trial_days') }}-day trial</x-ui.button>
                </form>
            </div>
        @endif

        {{-- Tier chooser --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($tiers as $key => $tier)
                <x-ui.card>
                    <div class="flex items-baseline justify-between">
                        <h2 class="text-base font-semibold text-slate-800">{{ $tier['name'] }}</h2>
                        @if ($workspace->tier() === $key)
                            <span class="inline-block rounded-full bg-brand-100 text-brand-700 px-2 py-0.5 text-xs">Current</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-slate-500">
                        @if ($tier['price_per_payee'] > 0)
                            {{ config('billing.currency') }} {{ number_format($tier['price_per_payee']) }} <span class="text-slate-400">/ active payee / mo</span>
                        @else
                            Free
                        @endif
                    </p>
                    <ul class="mt-3 space-y-1.5 text-sm text-slate-600">
                        @foreach ($tier['features'] as $feature)
                            <li class="flex gap-2"><span class="text-emerald-500">✓</span> {{ $feature }}</li>
                        @endforeach
                    </ul>
                    <div class="mt-4">
                        @if ($workspace->tier() === $key)
                            <x-ui.button variant="secondary" disabled>Current plan</x-ui.button>
                        @else
                            <form method="POST" action="{{ route('admin.billing.update') }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="tier" value="{{ $key }}" />
                                <x-ui.button>{{ $tier['price_per_payee'] > 0 ? 'Upgrade' : 'Switch' }} to {{ $tier['name'] }}</x-ui.button>
                            </form>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <p class="text-xs text-slate-400">
            Metering counts distinct workspace members who received a released payout this month. Included payees are deducted before the per-payee rate applies.
        </p>
    </div>
</x-app-layout>
