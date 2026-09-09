<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Rewards — run #{{ $run->id }}</h2>
            <a href="{{ route('admin.calc-runs.show', $run) }}" class="text-sm text-indigo-600 hover:text-indigo-900">Back to run</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        @include('admin.release._pipeline', ['transitionRoute' => route('admin.calc-runs.rewards.transition', $run)])

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            @if ($rewards->isEmpty())
                <p class="p-6 text-gray-500">No rewards in this run.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Rep</th>
                            <th class="px-6 py-3">Reward</th>
                            <th class="px-6 py-3 text-right">Amount</th>
                            <th class="px-6 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        @foreach ($rewards as $reward)
                            <tr>
                                <td class="px-6 py-4 text-gray-900">{{ $reward->user?->name ?? '—' }}</td>
                                <td class="px-6 py-4 text-gray-700">
                                    {{ $reward->reward_type->label() }}
                                    @if (isset($reward->meta['label']))<span class="text-gray-400">— {{ $reward->meta['label'] }}</span>@endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    {{ $reward->computed_amount !== null ? $reward->currency.' '.number_format((float) $reward->computed_amount, 2) : '—' }}
                                </td>
                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex px-2 text-xs font-semibold rounded-full',
                                        'bg-gray-100 text-gray-800' => $reward->status->value === 'pending',
                                        'bg-blue-100 text-blue-800' => $reward->status->value === 'reviewed',
                                        'bg-green-100 text-green-800' => $reward->status->value === 'released',
                                    ])>{{ $reward->status->label() }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
