@php
    $groups = [
        'Dashboards & analytics' => [
            ['admin.reports.overview', '📊 Analytics dashboard', 'Revenue, payout, attainment & liability at a glance.'],
            ['admin.reports.customers', 'Customer balances', 'Per customer: revenue, paid and outstanding — with a link to each customer\'s invoices.'],
            ['admin.reports.revenue', 'Revenue analytics', 'Revenue, profit & deals by month, quarter, year, customer, product, category, rep, source, currency or payment status.'],
        ],
        'Payout' => [
            ['admin.reports.payout-by-user', 'Payout by user', 'Total released payout per rep.'],
            ['admin.reports.payout-by-plan', 'Payout by plan', 'Total released payout per plan.'],
            ['admin.reports.payout-by-type', 'Payout by reward type', 'Commission, overrides, bonuses, adjustments…'],
            ['admin.reports.payout-by-month', 'Payout by month', 'Released payout per calendar month.'],
        ],
        'Crediting' => [
            ['admin.reports.crediting', 'Crediting explorer', 'Released credits by rep, plan, month, source, or any transaction field (product, customer…).'],
            ['admin.reports.uncredited', 'Uncredited transactions', 'Transactions no run credited — usually a missing alias.'],
            ['admin.reports.double-payments', 'Double-payment detection', 'Transactions with matching amount + date — possible duplicates.'],
        ],
        'Attainment' => [
            ['admin.reports.attainment', 'Attainment by user', 'Credited amount & payout per rep.'],
            ['admin.reports.quota-attainment', 'Quota attainment', 'Credited vs. plan quota, per rep and plan.'],
            ['admin.reports.attainment-by-plan', 'Attainment by plan', 'Reps, credited, payout & average attainment per plan.'],
            ['admin.reports.attainment-by-team', 'Attainment by team', "Each manager's direct reports, rolled up."],
            ['admin.reports.attainment-distribution', 'Attainment distribution', 'How many reps sit in each attainment band.'],
        ],
        'Finance' => [
            ['admin.reports.liability', 'Commission liability', 'Earned but not yet released, by rep.'],
            ['admin.reports.asc606', 'ASC 606 amortization', 'Straight-line recognition of released commissions over time.'],
        ],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Reports" subtitle="Every report exports to CSV (opens in Excel); BI tools can use the OData feed" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-5xl space-y-8">
        @unless ($hasData)
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                No released data yet. Payout, crediting and attainment reports summarise released credits and
                rewards — run a calculation and release its results first. Revenue analytics works as soon as
                transactions are imported.
            </div>
        @endunless

        @foreach ($groups as $group => $reports)
            <div>
                <h2 class="text-sm font-semibold text-slate-500 uppercase tracking-wide mb-3">{{ $group }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach ($reports as [$route, $name, $description])
                        <a href="{{ route($route) }}" class="block bg-white rounded-xl border border-slate-200 shadow-card p-5 hover:border-brand-300 hover:bg-slate-50">
                            <div class="font-medium text-slate-900">{{ $name }}</div>
                            <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach

        <x-ui.card>
            <h2 class="text-sm font-semibold text-slate-800">Power BI, Tableau &amp; Excel</h2>
            <p class="mt-1 text-sm text-slate-600">
                Connect with <span class="font-medium">Get Data → OData feed</span> to
                <code class="text-xs bg-slate-100 rounded px-1.5 py-0.5 break-all">{{ url('/api/v1/odata') }}</code>
                using <span class="font-medium">Basic</span> authentication: any user name, and your workspace API token
                (Settings → REST API) as the password. Entity sets: Payouts, Credits, Transactions.
            </p>
        </x-ui.card>
    </div>
</x-app-layout>
