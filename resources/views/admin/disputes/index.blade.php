<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Dispute queue') }}</h2>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            @if ($disputes->isEmpty())
                <p class="p-6 text-gray-500">No disputes.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Rep</th>
                            <th class="px-6 py-3">Category</th>
                            <th class="px-6 py-3">Plan</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Raised</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        @foreach ($disputes as $dispute)
                            <tr>
                                <td class="px-6 py-4 text-gray-900">{{ $dispute->user?->name }}</td>
                                <td class="px-6 py-4 text-gray-700">{{ $dispute->category }}</td>
                                <td class="px-6 py-4 text-gray-500">{{ $dispute->plan?->name ?? '—' }}</td>
                                <td class="px-6 py-4"><x-dispute-status :status="$dispute->status" /></td>
                                <td class="px-6 py-4 text-gray-500">{{ $dispute->created_at->diffForHumans() }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('disputes.show', $dispute) }}" class="text-indigo-600 hover:text-indigo-900">Open</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
