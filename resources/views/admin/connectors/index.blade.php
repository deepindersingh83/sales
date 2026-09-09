<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Integrations" subtitle="Data sources for transactions and BI" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 space-y-8 max-w-5xl">
        @foreach ($connectors as $category => $items)
            <div>
                <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ ucfirst($category) }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($items as $c)
                        <x-ui.card>
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-slate-800">{{ $c['label'] }}</span>
                                @if ($c['live'])
                                    <x-ui.badge color="green">Live</x-ui.badge>
                                @else
                                    <x-ui.badge color="amber">Configure</x-ui.badge>
                                @endif
                            </div>
                            @if ($c['live'])
                                <p class="mt-2 text-xs text-slate-500">Ready to use.</p>
                            @else
                                <p class="mt-2 text-xs text-slate-500">Needs credentials: {{ implode(', ', array_values($c['fields'])) }}.</p>
                            @endif
                        </x-ui.card>
                    @endforeach
                </div>
            </div>
        @endforeach

        <p class="text-xs text-slate-400">
            CSV, REST API/webhook, and BI feeds are live today. The remaining connectors are scaffolded
            (registered + config-aware) and activate once their driver + your credentials are supplied —
            see docs/INTEGRATIONS.md.
        </p>
    </div>
</x-app-layout>
