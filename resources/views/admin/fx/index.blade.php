<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="FX rates" subtitle="Effective-dated rates for multi-currency calculations" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <x-ui.card>
            <h2 class="text-sm font-semibold text-slate-800 mb-4">Add a rate</h2>
            <form method="POST" action="{{ route('admin.fx.store') }}" class="grid grid-cols-2 sm:grid-cols-5 gap-3 items-end">
                @csrf
                <div>
                    <x-input-label for="base_currency" value="From" />
                    <x-text-input id="base_currency" name="base_currency" maxlength="3" class="mt-1 block w-full uppercase" placeholder="EUR" required />
                </div>
                <div>
                    <x-input-label for="quote_currency" value="To" />
                    <x-text-input id="quote_currency" name="quote_currency" maxlength="3" class="mt-1 block w-full uppercase" placeholder="USD" required />
                </div>
                <div>
                    <x-input-label for="rate" value="Rate" />
                    <x-text-input id="rate" name="rate" type="number" step="0.0000001" class="mt-1 block w-full" placeholder="1.08" required />
                </div>
                <div>
                    <x-input-label for="effective_date" value="Effective" />
                    <x-text-input id="effective_date" name="effective_date" type="date" class="mt-1 block w-full" :value="now()->toDateString()" required />
                </div>
                <x-ui.button>Add</x-ui.button>
            </form>
            <p class="mt-2 text-xs text-slate-400">1 unit of “From” equals “Rate” units of “To”. Inverse rates are derived automatically.</p>
        </x-ui.card>

        <x-ui.card padding="p-0">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-3">Pair</th><th class="px-6 py-3">Rate</th><th class="px-6 py-3">Effective</th><th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rates as $r)
                        <tr>
                            <td class="px-6 py-3 font-medium text-slate-800">{{ $r->base_currency }} → {{ $r->quote_currency }}</td>
                            <td class="px-6 py-3 text-slate-700">{{ rtrim(rtrim(number_format((float) $r->rate, 7), '0'), '.') }}</td>
                            <td class="px-6 py-3 text-slate-500">{{ $r->effective_date->toDateString() }}</td>
                            <td class="px-6 py-3 text-right">
                                <form method="POST" action="{{ route('admin.fx.destroy', $r) }}" onsubmit="return confirm('Delete rate?')">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-600 hover:text-rose-800">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No workspace FX rates. Amounts are treated 1:1 until you add rates.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.card>
    </div>
</x-app-layout>
