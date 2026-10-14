<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Search" subtitle="Everything in this workspace" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl space-y-6">
        <form method="GET" action="{{ route('search.index') }}" class="flex gap-2">
            <x-text-input name="q" class="block w-full" :value="$term" placeholder="Search transactions, plans, products, tags…" autofocus />
            <x-ui.button>Search</x-ui.button>
        </form>

        @if ($term === '')
            <x-ui.card><p class="text-sm text-slate-500">Type a query above to search across the workspace.</p></x-ui.card>
        @elseif (empty($groups))
            <x-ui.card><p class="text-sm text-slate-500">No matches for “{{ $term }}”.</p></x-ui.card>
        @else
            @foreach ($groups as $label => $items)
                <x-ui.card padding="p-0">
                    <div class="px-6 py-3 border-b border-slate-100 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-800">{{ $label }}</h2>
                        <span class="text-xs text-slate-400">{{ $items->count() }}</span>
                    </div>
                    <ul class="divide-y divide-slate-50">
                        @foreach ($items as $item)
                            <li>
                                <a href="{{ $item['url'] }}" class="flex items-center justify-between px-6 py-3 hover:bg-slate-50 text-sm">
                                    <span class="text-slate-800">{{ $item['label'] }}</span>
                                    <span class="text-slate-400">{{ $item['meta'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endforeach
        @endif
    </div>
</x-app-layout>
