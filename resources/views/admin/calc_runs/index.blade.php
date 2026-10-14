<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Calculation runs') }}</h2>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            @if ($runs->isEmpty())
                <p class="p-6 text-gray-500">No calculation runs yet. Open a plan and click “Run calculation”.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Run</th>
                            <th class="px-6 py-3">Plan</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Started</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        @foreach ($runs as $run)
                            <tr>
                                <td class="px-6 py-4 font-mono text-gray-900">#{{ $run->id }}</td>
                                <td class="px-6 py-4 text-gray-700">{{ $run->plan->name }}</td>
                                <td class="px-6 py-4"><x-calc-status :status="$run->status" /></td>
                                <td class="px-6 py-4 text-gray-500">{{ optional($run->started_at)->diffForHumans() ?? '—' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.calc-runs.show', $run) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
