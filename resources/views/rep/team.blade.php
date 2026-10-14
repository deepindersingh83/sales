<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="My team" subtitle="Released attainment &amp; payout for your direct reports" />
    </x-slot>
    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl">
        @unless ($hasReports)
            <x-ui.card><p class="text-sm text-slate-500">You have no direct reports assigned. An admin sets reporting lines on the Team page.</p></x-ui.card>
        @else
            <x-ui.card padding="p-0">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50"><tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-3">Rep</th><th class="px-6 py-3 text-right">Credited</th><th class="px-6 py-3 text-right">Payout</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($team as $t)
                            <tr>
                                <td class="px-6 py-3 text-slate-800">{{ $t['name'] }}</td>
                                <td class="px-6 py-3 text-right text-slate-600">{{ number_format($t['credited'], 2) }}</td>
                                <td class="px-6 py-3 text-right font-medium text-slate-900">{{ number_format($t['payout'], 2) }}</td>
                            </tr>
                        @endforeach
                        <tr class="bg-slate-50 font-semibold">
                            <td class="px-6 py-3 text-slate-700">Team total</td>
                            <td class="px-6 py-3 text-right">{{ number_format($team->sum('credited'), 2) }}</td>
                            <td class="px-6 py-3 text-right">{{ number_format($team->sum('payout'), 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </x-ui.card>
        @endunless
    </div>
</x-app-layout>
