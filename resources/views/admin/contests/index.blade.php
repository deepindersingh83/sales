<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Contests" subtitle="Sales contests &amp; leaderboards" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <x-ui.card>
            <h2 class="text-sm font-semibold text-slate-800 mb-4">New contest</h2>
            <form method="POST" action="{{ route('admin.contests.store') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end">
                @csrf
                <div class="sm:col-span-2"><x-input-label for="name" value="Name" /><x-text-input id="name" name="name" class="mt-1 block w-full" required /></div>
                <div><x-input-label for="starts_on" value="Starts" /><x-text-input id="starts_on" name="starts_on" type="date" class="mt-1 block w-full" /></div>
                <div><x-input-label for="ends_on" value="Ends" /><x-text-input id="ends_on" name="ends_on" type="date" class="mt-1 block w-full" /></div>
                <x-ui.button>Create</x-ui.button>
                <div class="sm:col-span-3"><x-input-label for="prize" value="Prize (optional)" /><x-text-input id="prize" name="prize" class="mt-1 block w-full" /></div>
                <input type="hidden" name="metric" value="credited" />
            </form>
        </x-ui.card>

        <x-ui.card padding="p-0">
            @forelse ($contests as $c)
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                    <div>
                        <a href="{{ route('admin.contests.show', $c) }}" class="font-medium text-brand-700 hover:text-brand-800">{{ $c->name }}</a>
                        <div class="text-xs text-slate-400">
                            {{ optional($c->starts_on)->toDateString() ?? '—' }} → {{ optional($c->ends_on)->toDateString() ?? '—' }}
                            @if ($c->prize) · 🎁 {{ $c->prize }} @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.contests.destroy', $c) }}" onsubmit="return confirm('Delete?')">
                        @csrf @method('DELETE')
                        <button class="text-rose-600 hover:text-rose-800 text-sm">Delete</button>
                    </form>
                </div>
            @empty
                <p class="px-6 py-8 text-center text-slate-500">No contests yet.</p>
            @endforelse
        </x-ui.card>
    </div>
</x-app-layout>
