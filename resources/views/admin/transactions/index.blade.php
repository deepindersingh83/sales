<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Transactions') }}</h2>
            @can('import', App\Models\Transaction::class)
                <a href="{{ route('admin.imports.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    {{ __('Import CSV') }}
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            @if ($transactions->isEmpty())
                <p class="p-6 text-gray-500">No transactions yet. Import a CSV to get started.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">External ID</th>
                            <th class="px-6 py-3">Source</th>
                            <th class="px-6 py-3">Date</th>
                            <th class="px-6 py-3 text-right">Amount</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        @foreach ($transactions as $tx)
                            <tr class="{{ $tx->excluded ? 'opacity-50' : '' }}">
                                <td class="px-6 py-4 font-mono text-gray-900">{{ $tx->external_id }}</td>
                                <td class="px-6 py-4 text-gray-500">{{ $tx->source_system }}</td>
                                <td class="px-6 py-4 text-gray-500">{{ optional($tx->transaction_date)->toDateString() ?? '—' }}</td>
                                <td class="px-6 py-4 text-right">{{ $tx->currency }} {{ number_format((float) $tx->amount, 2) }}</td>
                                <td class="px-6 py-4 space-x-1">
                                    @if ($tx->excluded)<x-ui.badge color="rose">Excluded</x-ui.badge>@endif
                                    @if (! $tx->is_paid)<x-ui.badge color="amber">Unpaid</x-ui.badge>@endif
                                    @if (! $tx->excluded && $tx->is_paid)<x-ui.badge color="green">Active</x-ui.badge>@endif
                                </td>
                                <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                    @can('update', $tx)
                                        <form method="POST" action="{{ route('admin.transactions.exclude', $tx) }}" class="inline">@csrf<button class="text-xs text-slate-500 hover:text-slate-800">{{ $tx->excluded ? 'Include' : 'Exclude' }}</button></form>
                                        <form method="POST" action="{{ route('admin.transactions.paid', $tx) }}" class="inline">@csrf<button class="text-xs text-slate-500 hover:text-slate-800">{{ $tx->is_paid ? 'Mark unpaid' : 'Mark paid' }}</button></form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-6 py-4">{{ $transactions->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
