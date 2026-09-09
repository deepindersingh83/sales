<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Audit trail — run #{{ $run->id }}" subtitle="Every rule application, for dispute resolution">
            <x-slot name="actions">
                <x-ui.button variant="secondary" href="{{ route('admin.calc-runs.show', $run) }}">Back to run</x-ui.button>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 space-y-4">
        {{-- Step filters --}}
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.calc-runs.logs', $run) }}"
               @class(['px-3 py-1 rounded-full text-xs font-medium', 'bg-brand-600 text-white' => ! $activeStep, 'bg-white border border-slate-200 text-slate-600' => $activeStep])>All</a>
            @foreach ($steps as $step)
                <a href="{{ route('admin.calc-runs.logs', ['calcRun' => $run, 'step' => $step]) }}"
                   @class(['px-3 py-1 rounded-full text-xs font-medium', 'bg-brand-600 text-white' => $activeStep === $step, 'bg-white border border-slate-200 text-slate-600' => $activeStep !== $step])>{{ $step }}</a>
            @endforeach
        </div>

        <x-ui.card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                            <th class="px-4 py-3">Step</th>
                            <th class="px-4 py-3">Transaction</th>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3 text-right">Before</th>
                            <th class="px-4 py-3 text-right">After</th>
                            <th class="px-4 py-3">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($logs as $log)
                            <tr>
                                <td class="px-4 py-3"><x-ui.badge color="brand">{{ $log->step }}</x-ui.badge></td>
                                <td class="px-4 py-3 font-mono text-slate-500">{{ $log->transaction?->external_id ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $log->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-slate-500">{{ $log->amount_before !== null ? number_format((float) $log->amount_before, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-right font-medium text-slate-800">{{ $log->amount_after !== null ? number_format((float) $log->amount_after, 2) : '—' }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $log->description }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No log entries.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-slate-100">{{ $logs->links() }}</div>
        </x-ui.card>
    </div>
</x-app-layout>
