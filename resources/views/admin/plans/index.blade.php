<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Plans') }}</h2>
            @can('create', App\Models\Plan::class)
                <a href="{{ route('admin.plans.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    {{ __('New plan') }}
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            @if ($plans->isEmpty())
                <p class="p-6 text-gray-500">No plans yet.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Name</th>
                            <th class="px-6 py-3">Period</th>
                            <th class="px-6 py-3">Metric</th>
                            <th class="px-6 py-3">Tiers</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        @foreach ($plans as $plan)
                            <tr>
                                <td class="px-6 py-4 font-medium text-gray-900">{{ $plan->name }}</td>
                                <td class="px-6 py-4 text-gray-500 capitalize">{{ $plan->period_type }}</td>
                                <td class="px-6 py-4 text-gray-500 capitalize">{{ $plan->performance_metric }}</td>
                                <td class="px-6 py-4 text-gray-500">{{ $plan->tiers_count }}</td>
                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex px-2 text-xs font-semibold rounded-full',
                                        'bg-gray-100 text-gray-800' => $plan->status->value === 'draft',
                                        'bg-green-100 text-green-800' => $plan->status->value === 'active',
                                        'bg-yellow-100 text-yellow-800' => $plan->status->value === 'archived',
                                    ])>{{ $plan->status->label() }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.plans.show', $plan) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
