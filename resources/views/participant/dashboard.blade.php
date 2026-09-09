<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="My statement" subtitle="Your released credits and payouts">
            <x-slot name="actions">
                <x-ui.button href="{{ route('disputes.create') }}" variant="secondary">Raise a dispute</x-ui.button>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-ui.stat label="Total credited (released)" :value="number_format($totalCredited, 2)" />
            <x-ui.stat label="Total payout (released)" :value="number_format($totalPayout, 2)" accent="green" />
        </div>

        <x-ui.card padding="p-0">
            <div class="px-6 py-4 border-b border-slate-100"><h2 class="text-sm font-semibold text-slate-800">My payouts</h2></div>
            @forelse ($rewards as $reward)
                <div class="flex justify-between items-center px-6 py-3 border-b border-slate-50 text-sm">
                    <span class="text-slate-700">{{ $reward->reward_type->label() }} @if($reward->plan)<span class="text-slate-400">· {{ $reward->plan->name }}</span>@endif</span>
                    <span class="font-medium text-slate-900">{{ $reward->computed_amount !== null ? $reward->currency.' '.number_format((float) $reward->computed_amount, 2) : '—' }}</span>
                </div>
            @empty
                <p class="px-6 py-8 text-sm text-slate-500 text-center">No released payouts yet.</p>
            @endforelse
        </x-ui.card>

        <x-ui.card padding="p-0">
            <div class="px-6 py-4 border-b border-slate-100"><h2 class="text-sm font-semibold text-slate-800">My credited transactions</h2></div>
            @forelse ($credits as $credit)
                <div class="flex justify-between items-center px-6 py-3 border-b border-slate-50 text-sm">
                    <span class="font-mono text-slate-600">{{ $credit->transaction?->external_id ?? '—' }}
                        @if($credit->calcRun?->plan)<span class="font-sans text-slate-400">· {{ $credit->calcRun->plan->name }}</span>@endif
                    </span>
                    <span class="text-slate-900">{{ $credit->currency }} {{ number_format((float) $credit->credited_amount, 2) }}</span>
                </div>
            @empty
                <p class="px-6 py-8 text-sm text-slate-500 text-center">No released credits yet.</p>
            @endforelse
        </x-ui.card>
    </div>
</x-app-layout>
