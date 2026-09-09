<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="ASC 606 amortization" subtitle="Straight-line recognition of released commissions" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-2xl space-y-4">
        <form method="GET" action="{{ route('admin.reports.asc606') }}" class="flex items-center gap-2">
            <label class="text-sm text-slate-600">Amortize over</label>
            <input type="number" name="months" min="1" max="60" value="{{ $months }}" class="w-24 border-slate-300 rounded-lg shadow-sm text-sm" />
            <span class="text-sm text-slate-600">months</span>
            <x-ui.button variant="secondary">Update</x-ui.button>
        </form>

        <x-ui.stat label="Total released commission" :value="number_format($schedule['total'], 2)" />

        <x-ui.card padding="p-0">
            <div class="px-6 py-4 border-b border-slate-100"><h2 class="text-sm font-semibold text-slate-800">Monthly recognition</h2></div>
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-3">Month</th><th class="px-6 py-3 text-right">Recognized</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($schedule['by_month'] as $month => $amount)
                        <tr>
                            <td class="px-6 py-3 text-slate-700">{{ $month }}</td>
                            <td class="px-6 py-3 text-right font-medium">{{ number_format($amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-6 py-8 text-center text-slate-500">No released commissions to amortize.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.card>
        <p><a href="{{ route('admin.reports.index') }}" class="text-sm text-slate-600 hover:text-slate-900">← All reports</a></p>
    </div>
</x-app-layout>
