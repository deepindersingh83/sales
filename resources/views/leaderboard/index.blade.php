<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="🏆 Leaderboard" subtitle="Reps ranked by released credited attainment" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-2xl">
        <x-ui.card padding="p-0">
            @forelse ($standings as $s)
                <div @class([
                    'flex items-center gap-4 px-6 py-4 border-b border-slate-100',
                    'bg-brand-50/50' => $s['user'] === $me,
                ])>
                    <div @class([
                        'h-9 w-9 rounded-full inline-flex items-center justify-center font-bold text-sm shrink-0',
                        'bg-amber-100 text-amber-700' => $s['rank'] === 1,
                        'bg-slate-200 text-slate-600' => $s['rank'] === 2,
                        'bg-orange-100 text-orange-700' => $s['rank'] === 3,
                        'bg-slate-100 text-slate-500' => $s['rank'] > 3,
                    ])>
                        {{ $s['rank'] <= 3 ? ['','🥇','🥈','🥉'][$s['rank']] : $s['rank'] }}
                    </div>
                    <div class="flex-1 font-medium text-slate-800">{{ $s['user'] }}@if($s['user'] === $me)<span class="ml-2 text-xs text-brand-600">you</span>@endif</div>
                    <div class="font-semibold text-slate-900">{{ number_format($s['total'], 0) }}</div>
                </div>
            @empty
                <p class="px-6 py-10 text-center text-slate-500">No released credits yet — the leaderboard fills in as calculations are released.</p>
            @endforelse
        </x-ui.card>
    </div>
</x-app-layout>
