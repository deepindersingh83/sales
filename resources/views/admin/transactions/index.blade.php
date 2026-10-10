<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Transactions" subtitle="Imported deals and invoices">
            <x-slot name="actions">
                <x-ui.button variant="secondary" href="{{ route('admin.reports.customers') }}">Customer balances</x-ui.button>
                @can('import', App\Models\Transaction::class)
                    <x-ui.button href="{{ route('admin.imports.create') }}">Import CSV</x-ui.button>
                @endcan
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <form method="GET" action="{{ route('admin.transactions.index') }}" class="flex flex-wrap items-end gap-3">
            @if ($filters['customer'] !== '')
                <input type="hidden" name="customer" value="{{ $filters['customer'] }}" />
            @endif
            <div class="grow sm:grow-0 sm:w-72">
                <x-input-label for="q" value="Search" />
                <x-text-input id="q" name="q" class="mt-1 block w-full text-sm" :value="$filters['q']" placeholder="Invoice #, customer, reference…" />
            </div>
            <div>
                <x-input-label for="status" value="Payment" />
                <select id="status" name="status" class="mt-1 border-slate-300 rounded-lg shadow-sm text-sm">
                    <option value="">All</option>
                    <option value="paid" @selected($filters['status'] === 'paid')>Paid</option>
                    <option value="unpaid" @selected($filters['status'] === 'unpaid')>Unpaid / part-paid</option>
                </select>
            </div>
            <x-ui.button>Filter</x-ui.button>
            @if (array_filter($filters))
                <a href="{{ route('admin.transactions.index') }}" class="text-sm text-slate-500 hover:text-slate-800 pb-2">Clear</a>
            @endif
        </form>

        @if ($summary)
            @php $currency = $summary['currencies']->count() === 1 ? $summary['currencies']->first().' ' : ''; @endphp
            <x-ui.card>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-400">Customer</div>
                        <div class="text-lg font-semibold text-slate-900">{{ $filters['customer'] }}</div>
                    </div>
                    <div class="flex flex-wrap gap-6 text-sm">
                        <div><div class="text-slate-500">Invoices</div><div class="font-semibold text-slate-900 tabular-nums">{{ $summary['invoices'] }}</div></div>
                        <div><div class="text-slate-500">Revenue (ex tax)</div><div class="font-semibold text-slate-900 tabular-nums">{{ $currency }}{{ number_format($summary['revenue'], 2) }}</div></div>
                        <div><div class="text-slate-500">Paid</div><div class="font-semibold text-emerald-700 tabular-nums">{{ $currency }}{{ number_format($summary['revenue'] - $summary['outstanding'], 2) }}</div></div>
                        <div><div class="text-slate-500">Outstanding</div><div class="font-semibold text-amber-700 tabular-nums">{{ $currency }}{{ number_format($summary['outstanding'], 2) }}</div></div>
                    </div>
                </div>
                @if ($summary['currencies']->count() > 1)
                    <p class="mt-2 text-xs text-slate-500">This customer has invoices in several currencies; see Customer balances for totals converted to one currency.</p>
                @endif
            </x-ui.card>
        @endif

        <x-ui.card padding="p-0">
            @if ($transactions->isEmpty())
                <p class="p-6 text-slate-500">{{ array_filter($filters) ? 'No transactions match.' : 'No transactions yet. Connect Xero or import a CSV to get started.' }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                <th class="px-6 py-3">Invoice</th>
                                <th class="px-6 py-3">Customer</th>
                                <th class="px-6 py-3">Date</th>
                                <th class="px-6 py-3 text-right whitespace-nowrap" title="Invoice subtotal before tax">Amount ex tax</th>
                                <th class="px-6 py-3 text-right">Outstanding</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($transactions as $tx)
                                @php
                                    $payment = $tx->paymentStatus();
                                    $outstanding = (float) $tx->amount * $tx->outstandingFraction();
                                @endphp
                                <tr class="{{ $tx->excluded ? 'opacity-50' : '' }}">
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        <div class="text-slate-900">{{ $tx->reference() }}</div>
                                        <div class="text-xs text-slate-400">{{ $tx->source_system }}@if ($tx->reference() !== $tx->external_id) · <span class="font-mono" title="{{ $tx->external_id }}">{{ \Illuminate\Support\Str::limit($tx->external_id, 8) }}</span>@endif</div>
                                    </td>
                                    <td class="px-6 py-3 whitespace-nowrap">
                                        @if ($tx->customer())
                                            <a href="{{ route('admin.transactions.index', ['customer' => $tx->customer()]) }}" class="text-brand-700 hover:text-brand-900">{{ $tx->customer() }}</a>
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-slate-500 whitespace-nowrap">{{ optional($tx->transaction_date)->toDateString() ?? '—' }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums whitespace-nowrap">{{ $tx->currency }} {{ number_format((float) $tx->amount, 2) }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums whitespace-nowrap {{ $outstanding > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ number_format($outstanding, 2) }}</td>
                                    <td class="px-6 py-3 space-x-1 whitespace-nowrap">
                                        @if ($tx->excluded)
                                            <x-ui.badge color="rose">Excluded</x-ui.badge>
                                        @endif
                                        <x-ui.badge :color="match ($payment) { 'Paid' => 'green', 'Part-paid' => 'blue', default => 'amber' }">{{ $payment }}</x-ui.badge>
                                    </td>
                                    <td class="px-6 py-3 text-right space-x-2 whitespace-nowrap">
                                        @can('update', $tx)
                                            <form method="POST" action="{{ route('admin.transactions.exclude', $tx) }}" class="inline">@csrf<button class="text-xs text-slate-500 hover:text-slate-800">{{ $tx->excluded ? 'Include' : 'Exclude' }}</button></form>
                                            <form method="POST" action="{{ route('admin.transactions.paid', $tx) }}" class="inline">@csrf<button class="text-xs text-slate-500 hover:text-slate-800">{{ $tx->is_paid ? 'Mark unpaid' : 'Mark paid' }}</button></form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4">{{ $transactions->links() }}</div>
            @endif
        </x-ui.card>
    </div>
</x-app-layout>
