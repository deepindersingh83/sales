@php
    $targets = [
        'external_id' => 'External ID (required, dedup key)',
        'amount' => 'Amount (required)',
        'profit_amount' => 'Profit amount',
        'currency' => 'Currency',
        'transaction_date' => 'Transaction date',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Confirm column mapping') }}</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">Please fix the highlighted fields.</div>
        @endif

        <form method="POST" action="{{ route('admin.imports.store') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}" />

            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
                <div>
                    <x-input-label for="source_name" value="Import source name" />
                    <x-text-input id="source_name" name="source_name" class="block mt-1 w-full" :value="old('source_name', 'CSV import')" required />
                </div>

                <p class="text-sm text-gray-600">We auto-detected the mapping below — adjust any field as needed.</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach ($targets as $field => $label)
                        <div>
                            <x-input-label :for="'map_'.$field" :value="$label" />
                            <select id="map_{{ $field }}" name="mapping[{{ $field }}]"
                                    class="block mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm">
                                <option value="">— none —</option>
                                @foreach ($headers as $header)
                                    <option value="{{ $header }}" @selected(old("mapping.$field", $mapping[$field] ?? null) === $header)>{{ $header }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('mapping.'.$field)" class="mt-1" />
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-medium text-gray-900 mb-3">Preview (first {{ count($sample) }} rows)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50">
                            <tr>@foreach ($headers as $h)<th class="px-3 py-2 text-left font-medium text-gray-500">{{ $h }}</th>@endforeach</tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($sample as $row)
                                <tr>@foreach ($headers as $h)<td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $row[$h] ?? '' }}</td>@endforeach</tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.imports.create') }}" class="text-sm text-gray-600 hover:text-gray-900">Start over</a>
                <x-primary-button>Import transactions</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
