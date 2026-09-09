<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('My statement') }}</h2>
            <a href="{{ route('disputes.create') }}" class="text-sm text-indigo-600 hover:text-indigo-900">Raise a dispute</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="text-sm text-gray-500">Total credited (released)</div>
                <div class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($totalCredited, 2) }}</div>
            </div>
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="text-sm text-gray-500">Total payout (released)</div>
                <div class="mt-1 text-2xl font-semibold text-green-700">{{ number_format($totalPayout, 2) }}</div>
            </div>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-3">My payouts</h3>
            @forelse ($rewards as $reward)
                <div class="flex justify-between text-sm border-b border-gray-100 py-2">
                    <span>{{ $reward->reward_type->label() }} @if($reward->plan)<span class="text-gray-400">· {{ $reward->plan->name }}</span>@endif</span>
                    <span class="font-medium">{{ $reward->computed_amount !== null ? $reward->currency.' '.number_format((float) $reward->computed_amount, 2) : '—' }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">No released payouts yet.</p>
            @endforelse
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-3">My credited transactions</h3>
            @forelse ($credits as $credit)
                <div class="flex justify-between text-sm border-b border-gray-100 py-2">
                    <span class="font-mono text-gray-600">{{ $credit->transaction?->external_id ?? '—' }}
                        @if($credit->calcRun?->plan)<span class="text-gray-400 font-sans">· {{ $credit->calcRun->plan->name }}</span>@endif
                    </span>
                    <span>{{ $credit->currency }} {{ number_format((float) $credit->credited_amount, 2) }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">No released credits yet.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
