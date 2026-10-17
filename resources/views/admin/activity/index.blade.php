@php
    $show = fn ($value): string => \Illuminate\Support\Str::limit(
        is_string($value) ? $value : (string) json_encode($value),
        120,
    );
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Activity log" subtitle="Who changed what in this workspace">
            <x-slot name="actions">
                <x-ui.button variant="secondary" href="{{ route('admin.activity.index', array_merge(array_filter($filters), ['export' => 'csv'])) }}">Export CSV</x-ui.button>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <form method="GET" action="{{ route('admin.activity.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <x-input-label for="user_id" value="User" />
                <select id="user_id" name="user_id" class="mt-1 border-slate-300 rounded-lg shadow-sm text-sm">
                    <option value="">Anyone</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="action" value="Action starts with" />
                <x-text-input id="action" name="action" class="mt-1 block w-48 text-sm" :value="$filters['action'] ?? ''" placeholder="e.g. plan. or calc_run" />
            </div>
            <div>
                <x-input-label for="from" value="From" />
                <x-text-input id="from" name="from" type="date" class="mt-1 block text-sm" :value="$filters['from'] ?? ''" />
            </div>
            <div>
                <x-input-label for="to" value="To" />
                <x-text-input id="to" name="to" type="date" class="mt-1 block text-sm" :value="$filters['to'] ?? ''" />
            </div>
            <x-ui.button>Filter</x-ui.button>
            @if (array_filter($filters))
                <a href="{{ route('admin.activity.index') }}" class="text-sm text-slate-500 hover:text-slate-800 pb-2">Clear</a>
            @endif
        </form>

        <x-ui.card padding="p-0">
            @if ($logs->isEmpty())
                <p class="px-6 py-8 text-sm text-slate-500 text-center">No activity recorded yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                <th class="px-6 py-3">When</th>
                                <th class="px-6 py-3">User</th>
                                <th class="px-6 py-3">Action</th>
                                <th class="px-6 py-3">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-sm">
                            @foreach ($logs as $log)
                                <tr class="align-top">
                                    <td class="px-6 py-3 whitespace-nowrap text-slate-500" title="{{ $log->created_at?->toDateTimeString() }}">{{ $log->created_at?->format('j M Y H:i') }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap text-slate-800">{{ $log->user?->name ?? 'System' }}</td>
                                    <td class="px-6 py-3 whitespace-nowrap"><x-ui.badge color="brand">{{ $log->action }}</x-ui.badge></td>
                                    <td class="px-6 py-3 text-slate-700">
                                        {{ $log->description }}
                                        @if (! empty($log->properties['changes']))
                                            <ul class="mt-1 space-y-0.5 text-xs text-slate-500">
                                                @foreach ($log->properties['changes'] as $field => $change)
                                                    <li>
                                                        <span class="font-medium">{{ str_replace('_', ' ', $field) }}</span>:
                                                        @if (is_array($change) && array_key_exists('old', $change))
                                                            {{ $show($change['old']) }} → {{ $show($change['new']) }}
                                                        @else
                                                            changed
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>

        {{ $logs->links() }}
    </div>
</x-app-layout>
