<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Credits — run #{{ $run->id }}</h2>
            <a href="{{ route('admin.calc-runs.show', $run) }}" class="text-sm text-indigo-600 hover:text-indigo-900">Back to run</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        @include('admin.release._pipeline', ['transitionRoute' => route('admin.calc-runs.credits.transition', $run)])

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            @if ($credits->isEmpty())
                <p class="p-6 text-gray-500">No credits in this run.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Rep</th>
                            <th class="px-6 py-3">Transaction</th>
                            <th class="px-6 py-3 text-right">Credited</th>
                            <th class="px-6 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        @foreach ($credits as $credit)
                            <tr>
                                <td class="px-6 py-4 text-gray-900">{{ $credit->user?->name ?? '—' }}</td>
                                <td class="px-6 py-4 font-mono text-gray-500">{{ $credit->transaction?->external_id ?? '—' }}</td>
                                <td class="px-6 py-4 text-right">{{ $credit->currency }} {{ number_format((float) $credit->credited_amount, 2) }}</td>
                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex px-2 text-xs font-semibold rounded-full',
                                        'bg-gray-100 text-gray-800' => $credit->status->value === 'pending',
                                        'bg-blue-100 text-blue-800' => $credit->status->value === 'reviewed',
                                        'bg-green-100 text-green-800' => $credit->status->value === 'released',
                                    ])>{{ $credit->status->label() }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
