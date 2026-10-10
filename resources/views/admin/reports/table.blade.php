{{--
    Generic tabular report. Expects:
      $title, $subtitle, $rows (iterable of arrays), $columns (key => ['label', 'type'])
      where type is text|money|number|percent; optional $exportUrl and $filters
      (a view name rendered above the table).
--}}
<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="$title" :subtitle="$subtitle">
            @isset($exportUrl)
                <x-slot name="actions">
                    <x-ui.button variant="secondary" href="{{ $exportUrl }}">Export CSV</x-ui.button>
                </x-slot>
            @endisset
        </x-ui.page-header>
    </x-slot>
    <div class="p-4 sm:p-6 lg:p-8 max-w-5xl space-y-4">
        @isset($filters)
            @include($filters)
        @endisset

        <x-ui.card padding="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50"><tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        @foreach ($columns as $col)
                            <th class="px-6 py-3 {{ $col[1] === 'text' ? '' : 'text-right' }}">{{ $col[0] }}</th>
                        @endforeach
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($rows as $r)
                            <tr>
                                @foreach ($columns as $key => $col)
                                    @php $v = $r[$key] ?? null; @endphp
                                    <td class="px-6 py-3 {{ $col[1] === 'text' ? 'text-slate-800' : 'text-right text-slate-700 tabular-nums' }}">
                                        @if ($v === null)
                                            —
                                        @elseif ($col[1] === 'money')
                                            {{ number_format((float) $v, 2) }}
                                        @elseif ($col[1] === 'percent')
                                            {{ number_format((float) $v, 1) }}%
                                        @elseif ($col[1] === 'number')
                                            {{ number_format((float) $v) }}
                                        @else
                                            {{ $v }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($columns) }}" class="px-6 py-8 text-center text-slate-500">No data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.card>
        <p><a href="{{ route('admin.reports.index') }}" class="text-sm text-slate-600 hover:text-slate-900">← All reports</a></p>
    </div>
</x-app-layout>
