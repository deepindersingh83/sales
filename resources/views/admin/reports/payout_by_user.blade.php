<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Payout by user</h2>
            <a href="{{ route('admin.reports.payout-by-user', ['export' => 'csv']) }}" class="text-sm text-indigo-600 hover:text-indigo-900">Export CSV</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-3">User</th>
                        <th class="px-6 py-3 text-right">Total payout</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-sm">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-6 py-4 text-gray-900">{{ $row['user'] }}</td>
                            <td class="px-6 py-4 text-right font-medium">{{ $row['currency'] }} {{ number_format($row['total'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="px-6 py-4 text-gray-500">No released payouts.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-4"><a href="{{ route('admin.reports.index') }}" class="text-sm text-gray-600 hover:text-gray-900">← All reports</a></p>
    </div>
</x-app-layout>
