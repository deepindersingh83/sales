@php
    $user = auth()->user();
    $role = $user?->currentRole();
    $isAdmin = $role?->isAdmin() ?? false;
    $isFullAdmin = $role === \App\Enums\Role::FullAdmin;
    $workspace = app(\App\Support\WorkspaceContext::class)->get();
    $myWorkspaces = $user?->workspaces()->orderBy('name')->get() ?? collect();

    // Inline icon set (Heroicons outline, 20px).
    $ico = [
        'home' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a1.5 1.5 0 012.122 0L22.5 12M4.5 9.75v10.125A1.125 1.125 0 005.625 21h3.75V16.5a1.5 1.5 0 013 0V21h3.75A1.125 1.125 0 0021 19.875V9.75"/></svg>',
        'plans' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.42 48.42 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z"/></svg>',
        'tx' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>',
        'alias' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/></svg>',
        'calc' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V13.5zm0 2.25h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V18zm2.498-6.75h.007v.008h-.007v-.008zm0 2.25h.007v.008h-.007V13.5zm0 2.25h.007v.008h-.007v-.008zm0 2.25h.007v.008h-.007V18zm2.504-6.75h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V13.5zm0 2.25h.008v.008h-.008v-.008zM6.75 4.5h10.5a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25H6.75a2.25 2.25 0 01-2.25-2.25V6.75A2.25 2.25 0 016.75 4.5z"/></svg>',
        'dispute' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.76 9.76 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>',
        'report' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>',
        'team' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>',
    ];
@endphp

{{-- Brand (white-labelled per workspace when configured) --}}
@php
    $brandColor = $workspace?->brand_color;
    $brandLabel = $workspace?->brand_name ?: config('branding.name');
@endphp
<div class="h-16 flex items-center gap-2.5 px-5 border-b border-slate-200">
    @if ($workspace?->logo_url)
        <img src="{{ $workspace->logo_url }}" alt="{{ $brandLabel }}" class="h-8 w-8 rounded-lg object-cover" />
    @else
        <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-white font-bold" style="background-color: {{ $brandColor ?: '#4f46e5' }}">₡</span>
    @endif
    <span class="font-semibold text-slate-800 truncate">{{ $brandLabel }}</span>
</div>

{{-- Workspace switcher --}}
@if ($workspace)
    <div class="px-3 py-3 border-b border-slate-100" x-data="{ open: false }">
        <button @click="open = !open" class="w-full flex items-center justify-between gap-2 rounded-lg px-2 py-2 hover:bg-slate-100 transition">
            <span class="min-w-0 text-left">
                <span class="block text-[11px] uppercase tracking-wide text-slate-400">Company</span>
                <span class="block text-sm font-medium text-slate-700 truncate">{{ $workspace->name }}</span>
            </span>
            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 3a.75.75 0 01.55.24l3.25 3.5a.75.75 0 11-1.1 1.02L10 4.852 7.3 7.76a.75.75 0 01-1.1-1.02l3.25-3.5A.75.75 0 0110 3zm-3.8 9.24a.75.75 0 011.1-.02L10 15.148l2.7-2.908a.75.75 0 111.1 1.02l-3.25 3.5a.75.75 0 01-1.1 0l-3.25-3.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
        </button>
        <div x-show="open" @click.outside="open = false" x-transition class="mt-1 rounded-lg border border-slate-200 bg-white shadow-lg py-1" style="display:none">
            @foreach ($myWorkspaces as $ws)
                <form method="POST" action="{{ route('workspaces.switch', $ws) }}">
                    @csrf
                    <button class="w-full flex items-center gap-2 px-3 py-2 text-sm text-left hover:bg-slate-50 {{ $ws->id === $workspace->id ? 'text-brand-700 font-medium' : 'text-slate-700' }}">
                        <span class="truncate">{{ $ws->name }}</span>
                        @if ($ws->id === $workspace->id)<span class="ml-auto text-brand-600">✓</span>@endif
                    </button>
                </form>
            @endforeach
            <a href="{{ route('workspaces.create') }}" class="block px-3 py-2 text-sm text-brand-600 hover:bg-slate-50 border-t border-slate-100">+ New company</a>
        </div>
    </div>
@endif

<nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
    {{-- Global search --}}
    <form method="GET" action="{{ route('search.index') }}" class="px-1 pb-2">
        <div class="relative">
            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search…" class="w-full pl-8 pr-2 py-1.5 text-sm rounded-lg border-slate-200 focus:border-brand-400 focus:ring-brand-400" />
        </div>
    </form>

    <x-ui.nav-item :href="route('dashboard')" :active="request()->routeIs('dashboard')" :icon="$ico['home']">
        {{ $isAdmin ? 'Dashboard' : 'My statement' }}
    </x-ui.nav-item>

    @if ($isAdmin)
        <div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Compensation</div>
        <x-ui.nav-item :href="route('admin.plans.index')" :active="request()->routeIs('admin.plans.*')" :icon="$ico['plans']">Plans</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.transactions.index')" :active="request()->routeIs('admin.transactions.*') || request()->routeIs('admin.imports.*')" :icon="$ico['tx']">Transactions</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.import-sources.index')" :active="request()->routeIs('admin.import-sources.*')" :icon="$ico['tx']">Recurring imports</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.products.index')" :active="request()->routeIs('admin.products.*')" :icon="$ico['plans']">Products</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.aliases.index')" :active="request()->routeIs('admin.aliases.*')" :icon="$ico['alias']">Aliases</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.calc-runs.index')" :active="request()->routeIs('admin.calc-runs.*')" :icon="$ico['calc']">Calculations</x-ui.nav-item>

        <div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Operate</div>
        <x-ui.nav-item :href="route('admin.disputes.index')" :active="request()->routeIs('admin.disputes.*')" :icon="$ico['dispute']">Disputes</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.announcements.index')" :active="request()->routeIs('admin.announcements.*')" :icon="$ico['dispute']">Announcements</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.contests.index')" :active="request()->routeIs('admin.contests.*')" :icon="$ico['team']">Contests</x-ui.nav-item>
        <x-ui.nav-item :href="route('leaderboard.index')" :active="request()->routeIs('leaderboard.*')" :icon="$ico['report']">Leaderboard</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.surveys.index')" :active="request()->routeIs('admin.surveys.*')" :icon="$ico['dispute']">Surveys</x-ui.nav-item>
        <x-ui.nav-item :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*')" :icon="$ico['report']">Reports</x-ui.nav-item>

        @if ($isFullAdmin)
            <div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Administration</div>
            <x-ui.nav-item :href="route('admin.members.index')" :active="request()->routeIs('admin.members.*')" :icon="$ico['team']">Team</x-ui.nav-item>
            <x-ui.nav-item :href="route('admin.connectors.index')" :active="request()->routeIs('admin.connectors.*')" :icon="$ico['tx']">Integrations</x-ui.nav-item>
            <x-ui.nav-item :href="route('admin.fx.index')" :active="request()->routeIs('admin.fx.*')" :icon="$ico['report']">FX rates</x-ui.nav-item>
            <x-ui.nav-item :href="route('admin.billing.index')" :active="request()->routeIs('admin.billing.*')" :icon="$ico['calc']">Billing</x-ui.nav-item>
            <x-ui.nav-item :href="route('admin.settings.edit')" :active="request()->routeIs('admin.settings.*')" :icon="$ico['calc']">Settings</x-ui.nav-item>
        @endif
    @else
        <x-ui.nav-item :href="route('my.team')" :active="request()->routeIs('my.team')" :icon="$ico['team']">My team</x-ui.nav-item>
        <x-ui.nav-item :href="route('leaderboard.index')" :active="request()->routeIs('leaderboard.*')" :icon="$ico['report']">Leaderboard</x-ui.nav-item>
        <x-ui.nav-item :href="route('enrollments.index')" :active="request()->routeIs('enrollments.*')" :icon="$ico['plans']">Plans &amp; terms</x-ui.nav-item>
        <x-ui.nav-item :href="route('disputes.index')" :active="request()->routeIs('disputes.*')" :icon="$ico['dispute']">My disputes</x-ui.nav-item>
    @endif
</nav>

<div class="px-5 py-3 border-t border-slate-100 text-[11px] text-slate-400">
    Signed in as {{ $role?->label() ?? 'Member' }}
</div>
