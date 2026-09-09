<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Double-payment detection" subtitle="Transactions with a matching amount + date — possible duplicates" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl">
        <x-ui.card padding="p-0">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-3">Amount</th>
                        <th class="px-6 py-3">Date</th>
                        <th class="px-6 py-3">Count</th>
                        <th class="px-6 py-3">External IDs</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($suspects as $s)
                        <tr>
                            <td class="px-6 py-3 font-medium text-slate-800">{{ number_format($s['amount'], 2) }}</td>
                            <td class="px-6 py-3 text-slate-500">{{ $s['date'] ?? '—' }}</td>
                            <td class="px-6 py-3"><x-ui.badge color="amber">{{ $s['count'] }}</x-ui.badge></td>
                            <td class="px-6 py-3 font-mono text-slate-500">{{ implode(', ', $s['external_ids']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No suspected duplicates. 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.card>
        <p class="mt-4"><a href="{{ route('admin.reports.index') }}" class="text-sm text-slate-600 hover:text-slate-900">← All reports</a></p>
    </div>
</x-app-layout>
