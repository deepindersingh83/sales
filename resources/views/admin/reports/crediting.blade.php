<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Crediting by {{ $field }}</h2>
            <a href="{{ route('admin.reports.crediting', ['field' => $field, 'export' => 'csv']) }}" class="text-sm text-indigo-600 hover:text-indigo-900">Export CSV</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
        <form method="GET" action="{{ route('admin.reports.crediting') }}" class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Group by field:</label>
            <select name="field" onchange="this.form.submit()" class="border-gray-300 rounded-md shadow-sm text-sm">
                @foreach ($options as $opt)
                    <option value="{{ $opt }}" @selected($opt === $field)>{{ $opt }}</option>
                @endforeach
            </select>
        </form>

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-3">{{ ucfirst($field) }}</th>
                        <th class="px-6 py-3 text-right">Transactions</th>
                        <th class="px-6 py-3 text-right">Total credited</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-6 py-4 text-gray-900">{{ $row['key'] }}</td>
                            <td class="px-6 py-4 text-right text-gray-500">{{ $row['count'] }}</td>
                            <td class="px-6 py-4 text-right font-medium">{{ number_format($row['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-6 py-4 text-gray-500">No released credits.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p><a href="{{ route('admin.reports.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← All reports</a></p>
    </div>
</x-app-layout>
