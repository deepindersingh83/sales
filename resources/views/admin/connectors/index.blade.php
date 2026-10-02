<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Integrations" subtitle="Data sources for transactions and BI" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 space-y-8 max-w-5xl">
        <x-input-error :messages="$errors->get('xero')" />

        @foreach ($connectors as $category => $items)
            <div>
                <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ ucfirst($category) }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($items as $c)
                        <x-ui.card>
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-slate-800">{{ $c['label'] }}</span>
                                @if ($c['key'] === 'xero' && $xeroNeedsAttention)
                                    <x-ui.badge color="red">Needs attention</x-ui.badge>
                                @elseif ($c['key'] === 'xero' && $xeroSources->isNotEmpty())
                                    <x-ui.badge color="green">Connected</x-ui.badge>
                                @elseif ($c['key'] === 'xero' && ! $xeroConfigured)
                                    <x-ui.badge color="amber">Set up</x-ui.badge>
                                @elseif ($c['live'])
                                    <x-ui.badge color="green">Live</x-ui.badge>
                                @else
                                    <x-ui.badge color="slate">Coming soon</x-ui.badge>
                                @endif
                            </div>

                            @if ($c['key'] === 'xero')
                                @if ($xeroSources->isNotEmpty())
                                    <p class="mt-2 text-xs text-slate-500">
                                        {{ $xeroSources->pluck('config.tenant_name')->filter()->implode(', ') }}
                                    </p>
                                    @if ($xeroNeedsAttention)
                                        <p class="mt-1 text-xs text-rose-600">A connection needs attention.</p>
                                    @endif
                                @else
                                    <p class="mt-2 text-xs text-slate-500">Import sales invoices (net of tax) as transactions; paid status drives pay-when-paid.</p>
                                @endif
                                <div class="mt-3">
                                    <x-ui.button href="{{ route('admin.connectors.xero.show') }}" variant="{{ $xeroConfigured ? 'secondary' : 'primary' }}">
                                        {{ $xeroConfigured ? 'Manage' : 'Set up Xero' }}
                                    </x-ui.button>
                                </div>
                            @elseif ($c['key'] === 'odata')
                                <p class="mt-2 text-xs text-slate-500 break-all">Feed URL: {{ url('/api/v1/odata') }}</p>
                            @elseif ($c['live'])
                                <p class="mt-2 text-xs text-slate-500">Ready to use.</p>
                            @else
                                <p class="mt-2 text-xs text-slate-500">Not yet available — use CSV import or the REST API meanwhile.</p>
                            @endif
                        </x-ui.card>
                    @endforeach
                </div>
            </div>
        @endforeach

        <p class="text-xs text-slate-400">
            CSV, REST API/webhook, Xero, and the BI/OData feeds are live today. Other connectors are on the
            roadmap — see docs/INTEGRATIONS.md.
        </p>
    </div>
</x-app-layout>
