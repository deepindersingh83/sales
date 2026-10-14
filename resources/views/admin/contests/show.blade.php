<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="$contest->name" subtitle="Contest standings" />
    </x-slot>
    <div class="p-4 sm:p-6 lg:p-8 max-w-2xl space-y-4">
        @if ($contest->prize)<div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800">🎁 Prize: {{ $contest->prize }}</div>@endif
        <x-ui.card padding="p-0">
            @forelse ($standings as $s)
                <div class="flex items-center gap-4 px-6 py-3 border-b border-slate-100">
                    <span class="w-8 font-bold text-slate-500">{{ $s['rank'] }}</span>
                    <span class="flex-1 font-medium text-slate-800">{{ $s['user'] }}</span>
                    <span class="font-semibold text-slate-900">{{ number_format($s['total'], 0) }}</span>
                </div>
            @empty
                <p class="px-6 py-8 text-center text-slate-500">No results in this window yet.</p>
            @endforelse
        </x-ui.card>
        <a href="{{ route('admin.contests.index') }}" class="text-sm text-slate-600 hover:text-slate-900">← All contests</a>
    </div>
</x-app-layout>
