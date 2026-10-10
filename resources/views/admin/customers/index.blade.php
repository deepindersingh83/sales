<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Customers" subtitle="Synced from Xero contacts — assign a rep to credit all their invoices">
            <x-slot name="actions">
                <x-ui.button variant="secondary" href="{{ route('admin.reports.customers') }}">Customer balances</x-ui.button>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        <x-input-error :messages="$errors->all()" />

        <form method="GET" action="{{ route('admin.customers.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="grow sm:grow-0 sm:w-64">
                <x-input-label for="q" value="Search" />
                <x-text-input id="q" name="q" class="mt-1 block w-full text-sm" :value="$filters['q']" placeholder="Name or email…" />
            </div>
            <div>
                <x-input-label for="owner" value="Rep" />
                <select id="owner" name="owner" class="mt-1 border-slate-300 rounded-lg shadow-sm text-sm">
                    <option value="">Any</option>
                    <option value="none" @selected($filters['owner'] === 'none')>Unassigned</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}" @selected($filters['owner'] === (string) $member->id)>{{ $member->name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($tags->isNotEmpty())
                <div>
                    <x-input-label for="tag" value="Tag" />
                    <select id="tag" name="tag" class="mt-1 border-slate-300 rounded-lg shadow-sm text-sm">
                        <option value="">Any</option>
                        @foreach ($tags as $tag)
                            <option value="{{ $tag }}" @selected($filters['tag'] === $tag)>{{ $tag }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div>
                <x-input-label for="status" value="Status" />
                <select id="status" name="status" class="mt-1 border-slate-300 rounded-lg shadow-sm text-sm">
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="archived" @selected($filters['status'] === 'archived')>Archived</option>
                    <option value="all" @selected($filters['status'] === 'all')>All</option>
                </select>
            </div>
            <x-ui.button>Filter</x-ui.button>
        </form>

        <x-ui.card padding="p-0">
            @if ($customers->isEmpty())
                <p class="p-6 text-slate-500">
                    No customers {{ array_filter(\Illuminate\Support\Arr::except($filters, 'status')) ? 'match.' : 'yet. They appear after the next Xero sync (Integrations → Xero → Sync now).' }}
                </p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                <th class="px-6 py-3">Customer</th>
                                <th class="px-6 py-3 text-right">Invoices</th>
                                <th class="px-6 py-3 text-right whitespace-nowrap">Revenue ex tax</th>
                                <th class="px-6 py-3 text-right">Outstanding</th>
                                <th class="px-6 py-3">Rep &amp; tags</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($customers as $customer)
                                @php
                                    $total = $totals[$customer->external_id] ?? null;
                                    $currency = $total && $total['currencies']->count() === 1 ? $total['currencies']->first().' ' : '';
                                @endphp
                                <tr class="{{ $customer->status === 'archived' ? 'opacity-60' : '' }} align-top">
                                    <td class="px-6 py-3">
                                        <a href="{{ route('admin.transactions.index', ['customer' => $customer->name]) }}" class="font-medium text-brand-700 hover:text-brand-900">{{ $customer->name }}</a>
                                        @if ($customer->status === 'archived')
                                            <x-ui.badge color="slate" class="ml-1">Archived</x-ui.badge>
                                        @endif
                                        <div class="text-xs text-slate-500">{{ collect([$customer->email, $customer->phone])->filter()->implode(' · ') ?: '—' }}</div>
                                    </td>
                                    <td class="px-6 py-3 text-right tabular-nums text-slate-600">{{ $total['invoices'] ?? 0 }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums whitespace-nowrap">{{ $total ? $currency.number_format($total['revenue'], 2) : '—' }}</td>
                                    <td class="px-6 py-3 text-right tabular-nums whitespace-nowrap {{ ($total['outstanding'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $total ? $currency.number_format($total['outstanding'], 2) : '—' }}</td>
                                    <td class="px-6 py-3">
                                        @if ($canWrite)
                                            <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="flex flex-wrap items-center gap-2">
                                                @csrf @method('PUT')
                                                <select name="owner_user_id" class="border-slate-300 rounded-lg shadow-sm text-sm" aria-label="Rep for {{ $customer->name }}">
                                                    <option value="">No rep</option>
                                                    @foreach ($members as $member)
                                                        <option value="{{ $member->id }}" @selected((int) $customer->owner_user_id === (int) $member->id)>{{ $member->name }}</option>
                                                    @endforeach
                                                </select>
                                                <input name="tags" value="{{ $customer->tags->pluck('name')->implode(', ') }}" placeholder="tags, comma separated"
                                                       class="w-44 border-slate-300 rounded-lg shadow-sm text-sm" aria-label="Tags for {{ $customer->name }}" />
                                                <x-ui.button variant="secondary">Save</x-ui.button>
                                            </form>
                                        @else
                                            <span class="text-slate-700">{{ $customer->owner?->name ?? 'No rep' }}</span>
                                            @foreach ($customer->tags as $tag)
                                                <x-ui.badge color="slate">{{ $tag->name }}</x-ui.badge>
                                            @endforeach
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4">{{ $customers->links() }}</div>
            @endif
        </x-ui.card>

        <p class="text-xs text-slate-400">
            Assigning a rep credits that rep with every invoice and credit note for the customer (via a contact alias).
            If an alias on a Xero tracking category such as "Sales Rep" also matches the same invoices, both apply — use one approach per customer, or set split percentages under Aliases.
        </p>
    </div>
</x-app-layout>
