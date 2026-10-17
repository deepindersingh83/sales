@php
    $query = array_filter(['from' => $from, 'to' => $to, 'sort' => $sort]);
    $sortLink = fn (string $key) => route('admin.reports.customers', array_merge($query, ['sort' => $key]));
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Customer balances" subtitle="Revenue, paid, outstanding and overdue per customer (ex tax, {{ $kpis['currency'] }})">
            <x-slot name="actions">
                <x-ui.button variant="secondary" href="{{ route('admin.reports.customers', $query + ['export' => 'csv']) }}">Export CSV</x-ui.button>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-6xl">
        <form method="GET" action="{{ route('admin.reports.customers') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="sort" value="{{ $sort }}" />
            <div>
                <x-input-label for="from" value="Invoice date from" />
                <x-text-input id="from" name="from" type="date" class="mt-1 text-sm" :value="$from" />
            </div>
            <div>
                <x-input-label for="to" value="To" />
                <x-text-input id="to" name="to" type="date" class="mt-1 text-sm" :value="$to" />
            </div>
            <x-ui.button>Apply</x-ui.button>
            <x-input-error :messages="$errors->get('to')" />
        </form>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            <x-ui.stat label="Revenue" :value="number_format($kpis['revenue'], 2)" />
            <x-ui.stat label="Paid" :value="number_format($kpis['paid'], 2)" accent="green" />
            <x-ui.stat label="Outstanding" :value="number_format($kpis['outstanding'], 2)" />
            <x-ui.stat label="Overdue" :value="number_format($kpis['overdue'], 2)" />
            <x-ui.stat label="Customers" :value="$kpis['customers']" />
        </div>

        <x-ui.card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50"><tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-3"><a href="{{ $sortLink('customer') }}" class="hover:text-slate-800">Customer</a></th>
                        <th class="px-6 py-3 text-right">Invoices</th>
                        <th class="px-6 py-3 text-right"><a href="{{ $sortLink('revenue') }}" class="hover:text-slate-800">Revenue{{ $sort === 'revenue' ? ' ↓' : '' }}</a></th>
                        <th class="px-6 py-3 text-right"><a href="{{ $sortLink('paid') }}" class="hover:text-slate-800">Paid{{ $sort === 'paid' ? ' ↓' : '' }}</a></th>
                        <th class="px-6 py-3 text-right"><a href="{{ $sortLink('outstanding') }}" class="hover:text-slate-800">Outstanding{{ $sort === 'outstanding' ? ' ↓' : '' }}</a></th>
                        @foreach (\App\Services\Reporting\RevenueAnalytics::AGING_BUCKETS as $bucket => $bucketLabel)
                            <th class="px-4 py-3 text-right whitespace-nowrap">
                                @if ($bucket === 'd1_30')
                                    <a href="{{ $sortLink('overdue') }}" class="hover:text-slate-800" title="Sort by total overdue">{{ $bucketLabel }}{{ $sort === 'overdue' ? ' ↓' : '' }}</a>
                                @else
                                    {{ $bucketLabel }}
                                @endif
                            </th>
                        @endforeach
                        <th class="px-6 py-3 w-36">Paid %</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            @php $paidPct = $r['revenue'] > 0 ? max(0, min(100, $r['paid'] / $r['revenue'] * 100)) : 0; @endphp
                            <tr>
                                <td class="px-6 py-3">
                                    @if ($r['key'] !== '—')
                                        <a href="{{ route('admin.transactions.index', ['customer' => $r['key']]) }}" class="text-brand-700 hover:text-brand-900">{{ $r['key'] }}</a>
                                    @else
                                        <span class="text-slate-500">No customer</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right text-slate-600 tabular-nums">{{ $r['deals'] }}</td>
                                <td class="px-6 py-3 text-right text-slate-900 tabular-nums">{{ number_format($r['revenue'], 2) }}</td>
                                <td class="px-6 py-3 text-right text-emerald-700 tabular-nums">{{ number_format($r['paid'], 2) }}</td>
                                <td class="px-6 py-3 text-right font-medium tabular-nums {{ $r['outstanding'] > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ number_format($r['outstanding'], 2) }}</td>
                                @foreach ($r['aging'] as $bucket => $amount)
                                    <td class="px-4 py-3 text-right tabular-nums {{ $amount == 0 ? 'text-slate-300' : ($bucket === 'current' ? 'text-slate-700' : ($bucket === 'd90_plus' || $bucket === 'd61_90' ? 'text-rose-700 font-medium' : 'text-amber-700')) }}">{{ $amount == 0 ? '—' : number_format($amount, 2) }}</td>
                                @endforeach
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-2 rounded-full bg-amber-100 overflow-hidden"><div class="h-full bg-emerald-500" style="width: {{ round($paidPct, 1) }}%"></div></div>
                                        <span class="text-xs text-slate-500 tabular-nums w-10 text-right">{{ round($paidPct) }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="px-6 py-8 text-center text-slate-500">No transactions in this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <p class="text-xs text-slate-400">Amounts are invoice subtotals before tax, converted to {{ $kpis['currency'] }}. Part-paid Xero invoices are split by their amount due, and credit notes reduce the balance. Ageing is days past each invoice's Xero due date as of today; items without a due date count as current. Voided and excluded transactions are ignored.</p>
        <p><a href="{{ route('admin.reports.index') }}" class="text-sm text-slate-600 hover:text-slate-900">← All reports</a></p>
    </div>
</x-app-layout>
