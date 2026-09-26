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
                                @if ($c['key'] === 'xero' && $xeroSources->isNotEmpty())
                                    <x-ui.badge color="green">Connected</x-ui.badge>
                                @elseif ($c['live'])
                                    <x-ui.badge color="green">Live</x-ui.badge>
                                @else
                                    <x-ui.badge color="slate">Coming soon</x-ui.badge>
                                @endif
                            </div>

                            @if ($c['key'] === 'xero')
                                @if ($xeroSources->isNotEmpty())
                                    <p class="mt-2 text-xs text-slate-500">
                                        {{ $xeroSources->pluck('config.tenant_name')->filter()->implode(', ') }} —
                                        sales invoices sync daily.
                                        <a href="{{ route('admin.import-sources.index') }}" class="text-brand-600 hover:text-brand-800">Manage</a>
                                    </p>
                                @else
                                    <p class="mt-2 text-xs text-slate-500">Import sales invoices (net of tax) as transactions; paid status drives pay-when-paid.</p>
                                @endif
                                @if ($xeroConfigured)
                                    <div class="mt-3">
                                        <x-ui.button href="{{ route($c['connect_route']) }}" variant="{{ $xeroSources->isNotEmpty() ? 'secondary' : 'primary' }}">
                                            {{ $xeroSources->isNotEmpty() ? 'Reconnect / add organisation' : 'Connect Xero' }}
                                        </x-ui.button>
                                    </div>
                                @else
                                    <p class="mt-2 text-xs text-amber-700">Server setup needed: set XERO_CLIENT_ID and XERO_CLIENT_SECRET.</p>
                                @endif
                            @elseif ($c['key'] === 'odata')
                                <p class="mt-2 text-xs text-slate-500 break-all">Feed URL: {{ url('/api/odata') }}</p>
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
