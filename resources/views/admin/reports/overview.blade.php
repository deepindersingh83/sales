@php
    $maxUser = max(1, $topUsers->max('total') ?? 1);
    $maxType = max(1, $byType->max('total') ?? 1);
    $maxMonth = max(1, $byMonth->max('total') ?? 1);
    $maxRevenueMonth = max(1, $revenueByMonth->max('revenue') ?? 1);
    $maxBand = max(1, $distribution->max('count') ?? 1);
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Analytics" subtitle="Revenue, payout, attainment &amp; liability at a glance" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-ui.stat label="Revenue ({{ $revenueKpis['currency'] }})" :value="number_format($revenueKpis['revenue'], 2)" accent="green" />
            <x-ui.stat label="Deals · avg size" :value="$revenueKpis['deals'].' · '.number_format($revenueKpis['average_deal'], 0)" />
            <x-ui.stat label="Customers" :value="$revenueKpis['customers']" />
            <x-ui.stat label="Payout / revenue" :value="$revenueKpis['revenue'] > 0 ? round($totalReleased / $revenueKpis['revenue'] * 100, 1).'%' : '—'" />
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-ui.stat label="Total released payout" :value="number_format($totalReleased, 2)" accent="green" />
            <x-ui.stat label="Accrued liability" :value="number_format($totalLiability, 2)" />
            <x-ui.stat label="Reps paid" :value="$topUsers->count()" />
            <x-ui.stat label="Payout types" :value="$byType->count()" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Top reps --}}
            <x-ui.card>
                <h2 class="text-sm font-semibold text-slate-800 mb-4">Top reps by payout</h2>
                <div class="space-y-2.5">
                    @forelse ($topUsers as $u)
                        <div>
                            <div class="flex justify-between text-sm"><span class="text-slate-700">{{ $u['user'] }}</span><span class="font-medium text-slate-900">{{ number_format($u['total'], 0) }}</span></div>
                            <div class="mt-1 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-gradient-to-r from-brand-500 to-violet-500" style="width: {{ round($u['total'] / $maxUser * 100, 1) }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No released payouts yet.</p>
                    @endforelse
                </div>
            </x-ui.card>

            {{-- By type --}}
            <x-ui.card>
                <h2 class="text-sm font-semibold text-slate-800 mb-4">Payout by type</h2>
                <div class="space-y-2.5">
                    @forelse ($byType as $t)
                        <div>
                            <div class="flex justify-between text-sm"><span class="text-slate-700">{{ $t['type'] }}</span><span class="font-medium text-slate-900">{{ number_format($t['total'], 0) }}</span></div>
                            <div class="mt-1 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-emerald-500" style="width: {{ round($t['total'] / $maxType * 100, 1) }}%"></div></div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">No data.</p>
                    @endforelse
                </div>
            </x-ui.card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Revenue trend --}}
            <x-ui.card>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-slate-800">Revenue by month</h2>
                    <a href="{{ route('admin.reports.revenue') }}" class="text-xs text-brand-600 hover:text-brand-800">Revenue analytics →</a>
                </div>
                @if ($revenueByMonth->isEmpty())
                    <p class="text-sm text-slate-500">No transactions yet.</p>
                @else
                    <div class="flex items-end gap-2 h-40">
                        @foreach ($revenueByMonth as $m)
                            <div class="flex-1 flex flex-col items-center justify-end h-full">
                                <div class="w-full rounded-t bg-emerald-500" style="height: {{ max(2, round(max(0, $m['revenue']) / $maxRevenueMonth * 100, 1)) }}%" title="{{ $m['key'] }}: {{ number_format($m['revenue'], 2) }}"></div>
                                <div class="mt-1 text-[10px] text-slate-400">{{ substr($m['key'], 2) }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>

            {{-- Quota attainment distribution --}}
            <x-ui.card>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-slate-800">Quota attainment</h2>
                    <a href="{{ route('admin.reports.quota-attainment') }}" class="text-xs text-brand-600 hover:text-brand-800">By rep →</a>
                </div>
                @if ($distribution->sum('count') === 0)
                    <p class="text-sm text-slate-500">No plans with a quota have released credits yet.</p>
                @else
                    <div class="space-y-2.5">
                        @foreach ($distribution as $band)
                            <div>
                                <div class="flex justify-between text-sm"><span class="text-slate-700">{{ $band['band'] }}</span><span class="font-medium text-slate-900">{{ $band['count'] }}</span></div>
                                <div class="mt-1 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-violet-500" style="width: {{ round($band['count'] / $maxBand * 100, 1) }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        </div>

        {{-- By month (column chart) --}}
        <x-ui.card>
            <h2 class="text-sm font-semibold text-slate-800 mb-4">Payout by month</h2>
            @if ($byMonth->isEmpty())
                <p class="text-sm text-slate-500">No data.</p>
            @else
                <div class="flex items-end gap-3 h-48">
                    @foreach ($byMonth as $m)
                        <div class="flex-1 flex flex-col items-center justify-end">
                            <div class="w-full rounded-t bg-gradient-to-t from-brand-600 to-brand-400" style="height: {{ max(4, round($m['total'] / $maxMonth * 100, 1)) }}%" title="{{ number_format($m['total'], 2) }}"></div>
                            <div class="mt-2 text-[10px] text-slate-400 -rotate-45 origin-top-left whitespace-nowrap">{{ $m['month'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <p><a href="{{ route('admin.reports.index') }}" class="text-sm text-slate-600 hover:text-slate-900">← All reports</a></p>
    </div>
</x-app-layout>
