<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Simulation" :subtitle="$plan->name.' — projected, not saved'" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl space-y-6">
        <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
            This is a what-if projection. Nothing was saved — no run, credits, or rewards were created.
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <x-ui.stat label="Reps credited" :value="$summary['reps']" />
            <x-ui.stat label="Credits" :value="$summary['credited']" />
            <x-ui.stat label="Uncredited txns" :value="$summary['uncredited']" />
            <x-ui.stat label="Projected commission" :value="number_format($summary['commission_total'], 2)" accent="green" />
        </div>

        <x-ui.card padding="p-0">
            <div class="px-6 py-4 border-b border-slate-100"><h2 class="text-sm font-semibold text-slate-800">Projected rewards</h2></div>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-3">Rep</th><th class="px-6 py-3">Type</th><th class="px-6 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rewards as $r)
                        <tr>
                            <td class="px-6 py-3 text-slate-800">{{ $r['user'] }}</td>
                            <td class="px-6 py-3 text-slate-600">{{ str($r['reward_type'])->headline() }}</td>
                            <td class="px-6 py-3 text-right font-medium">{{ $r['amount'] !== null ? $r['currency'].' '.number_format($r['amount'], 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-slate-500">No projected rewards — check aliases and transactions.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.card>

        <a href="{{ route('admin.plans.show', $plan) }}" class="text-sm text-slate-600 hover:text-slate-900">← Back to plan</a>
    </div>
</x-app-layout>
