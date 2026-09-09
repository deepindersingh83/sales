<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Attainment by user" subtitle="Released credited amount &amp; payout per rep">
            <x-slot name="actions">
                <x-ui.button variant="secondary" href="{{ route('admin.reports.attainment', ['export' => 'csv']) }}">Export CSV</x-ui.button>
            </x-slot>
        </x-ui.page-header>
    </x-slot>
    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl">
        <x-ui.card padding="p-0">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50"><tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                    <th class="px-6 py-3">User</th><th class="px-6 py-3 text-right">Credited</th><th class="px-6 py-3 text-right">Payout</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $r)
                        <tr>
                            <td class="px-6 py-3 text-slate-800">{{ $r['user'] }}</td>
                            <td class="px-6 py-3 text-right text-slate-600">{{ number_format($r['credited'], 2) }}</td>
                            <td class="px-6 py-3 text-right font-medium text-slate-900">{{ number_format($r['payout'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-8 text-center text-slate-500">No released data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.card>
        <p class="mt-4"><a href="{{ route('admin.reports.index') }}" class="text-sm text-slate-600 hover:text-slate-900">← All reports</a></p>
    </div>
</x-app-layout>
