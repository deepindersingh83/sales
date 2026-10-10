@php
    $maxRevenue = max(1, $rows->max('revenue') ?? 1);
    $query = array_filter(['dimension' => $dimension, 'from' => $from, 'to' => $to]);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Revenue analytics" subtitle="All imported transactions, converted to {{ $kpis['currency'] }}">
            <x-slot name="actions">
                <x-ui.button variant="secondary" href="{{ route('admin.reports.revenue', $query + ['export' => 'csv']) }}">Export CSV</x-ui.button>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-6xl">
        <form method="GET" action="{{ route('admin.reports.revenue') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="dimension" value="Breakdown" />
                <select id="dimension" name="dimension" class="mt-1 border-slate-300 rounded-lg shadow-sm text-sm">
                    @foreach (\App\Services\Reporting\RevenueAnalytics::DIMENSIONS as $key => $dimLabel)
                        <option value="{{ $key }}" @selected($key === $dimension)>{{ $dimLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="from" value="From" />
                <x-text-input id="from" name="from" type="date" class="mt-1 text-sm" :value="$from" />
            </div>
            <div>
                <x-input-label for="to" value="To" />
                <x-text-input id="to" name="to" type="date" class="mt-1 text-sm" :value="$to" />
            </div>
            <x-ui.button>Apply</x-ui.button>
            <x-input-error :messages="$errors->get('to')" />
        </form>

        <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
            <x-ui.stat label="Revenue" :value="number_format($kpis['revenue'], 2)" accent="green" />
            <x-ui.stat label="Growth vs prior period" :value="$kpis['growth'] === null ? '—' : ($kpis['growth'] > 0 ? '+' : '').$kpis['growth'].'%'" />
            <x-ui.stat label="Profit" :value="number_format($kpis['profit'], 2)" />
            <x-ui.stat label="Margin" :value="$kpis['margin'] === null ? '—' : $kpis['margin'].'%'" />
            <x-ui.stat label="Deals · avg size" :value="$kpis['deals'].' · '.number_format($kpis['average_deal'], 0)" />
            <x-ui.stat label="Customers" :value="$kpis['customers']" />
        </div>

        <x-ui.card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50"><tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-3">{{ $label }}</th>
                        <th class="px-6 py-3 w-1/3"></th>
                        <th class="px-6 py-3 text-right">Revenue</th>
                        <th class="px-6 py-3 text-right">Paid</th>
                        <th class="px-6 py-3 text-right">Outstanding</th>
                        <th class="px-6 py-3 text-right">Profit</th>
                        <th class="px-6 py-3 text-right">Deals</th>
                        <th class="px-6 py-3 text-right">Share</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            <tr>
                                <td class="px-6 py-3 text-slate-800">{{ $r['key'] }}</td>
                                <td class="px-6 py-3">
                                    <div class="h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-brand-500" style="width: {{ max(0, round($r['revenue'] / $maxRevenue * 100, 1)) }}%"></div></div>
                                </td>
                                <td class="px-6 py-3 text-right font-medium text-slate-900 tabular-nums">{{ number_format($r['revenue'], 2) }}</td>
                                <td class="px-6 py-3 text-right text-emerald-700 tabular-nums">{{ number_format($r['paid'], 2) }}</td>
                                <td class="px-6 py-3 text-right tabular-nums {{ $r['outstanding'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ number_format($r['outstanding'], 2) }}</td>
                                <td class="px-6 py-3 text-right text-slate-600 tabular-nums">{{ number_format($r['profit'], 2) }}</td>
                                <td class="px-6 py-3 text-right text-slate-600 tabular-nums">{{ $r['deals'] }}</td>
                                <td class="px-6 py-3 text-right text-slate-600 tabular-nums">{{ number_format($r['share'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-6 py-8 text-center text-slate-500">No transactions in this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <p class="text-xs text-slate-400">Excluded transactions are ignored. Rep revenue is attributed by released credit share; revenue no run has credited appears as “Uncredited”.</p>
        <p><a href="{{ route('admin.reports.index') }}" class="text-sm text-slate-600 hover:text-slate-900">← All reports</a></p>
    </div>
</x-app-layout>
