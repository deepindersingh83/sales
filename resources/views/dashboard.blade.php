<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Dashboard" :subtitle="'Signed in as '.$role->label()" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6">
        {{-- Stat tiles --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('admin.plans.index') }}"><x-ui.stat label="Plans" :value="$stats['plans']" hint="View plans →" /></a>
            <a href="{{ route('admin.transactions.index') }}"><x-ui.stat label="Transactions" :value="number_format($stats['transactions'])" hint="View transactions →" /></a>
            <a href="{{ route('admin.calc-runs.index') }}"><x-ui.stat label="Pending credits" :value="number_format($stats['pendingCredits'])" hint="Awaiting review →" /></a>
            <a href="{{ route('admin.disputes.index') }}"><x-ui.stat label="Open disputes" :value="$stats['openDisputes']" hint="Triage queue →" /></a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Quick actions --}}
            <x-ui.card class="lg:col-span-1">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">Quick actions</h2>
                <div class="space-y-2">
                    @can('create', App\Models\Plan::class)
                        <x-ui.button href="{{ route('admin.plans.create') }}" class="w-full">New plan</x-ui.button>
                    @endcan
                    @can('import', App\Models\Transaction::class)
                        <x-ui.button variant="secondary" href="{{ route('admin.imports.create') }}" class="w-full">Import transactions (CSV)</x-ui.button>
                    @endcan
                    <x-ui.button variant="secondary" href="{{ route('admin.reports.index') }}" class="w-full">View reports</x-ui.button>
                </div>
            </x-ui.card>

            {{-- Recent runs --}}
            <x-ui.card class="lg:col-span-2" padding="p-0">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-800">Recent calculation runs</h2>
                    <a href="{{ route('admin.calc-runs.index') }}" class="text-sm text-brand-600 hover:text-brand-700">View all</a>
                </div>
                @if ($recentRuns->isEmpty())
                    <p class="px-6 py-8 text-sm text-slate-500 text-center">No runs yet — open a plan and click “Run calculation”.</p>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($recentRuns as $run)
                            <a href="{{ route('admin.calc-runs.show', $run) }}" class="flex items-center justify-between px-6 py-3 hover:bg-slate-50">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-slate-800 truncate">{{ $run->plan->name }}</div>
                                    <div class="text-xs text-slate-400">Run #{{ $run->id }} · {{ $run->created_at->diffForHumans() }}</div>
                                </div>
                                <x-calc-status :status="$run->status" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-app-layout>
