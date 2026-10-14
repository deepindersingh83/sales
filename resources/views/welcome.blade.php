@php $brand = config('branding.name'); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brand }} — {{ config('branding.tagline') }}</title>
    <meta name="description" content="{{ $brand }} automates sales commission and incentive compensation — plans, crediting, calculations, and transparent rep dashboards.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-slate-700 antialiased bg-white">

    {{-- ===== Nav ===== --}}
    <header x-data="{ open: false }" class="sticky top-0 z-40 bg-white/80 backdrop-blur border-b border-slate-100">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2.5">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-violet-600 text-white font-bold text-lg">₡</span>
                <span class="font-bold text-slate-800">{{ $brand }}</span>
            </a>

            <div class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-600">
                <a href="#features" class="hover:text-slate-900">Features</a>
                <a href="#how" class="hover:text-slate-900">How it works</a>
                <a href="#stack" class="hover:text-slate-900">Platform</a>
            </div>

            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700">Go to dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hidden sm:inline-flex items-center px-4 py-2 rounded-lg text-slate-700 text-sm font-medium hover:bg-slate-100">Log in</a>
                    <a href="{{ route('register') }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-brand-600 text-white text-sm font-medium hover:bg-brand-700 shadow-sm">Get started</a>
                @endauth
            </div>
        </nav>
    </header>

    {{-- ===== Hero ===== --}}
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-brand-50/60 via-white to-white"></div>
        <div class="absolute -top-24 -right-24 -z-10 h-96 w-96 rounded-full bg-violet-200/40 blur-3xl"></div>
        <div class="absolute top-40 -left-24 -z-10 h-80 w-80 rounded-full bg-brand-200/40 blur-3xl"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-16 pb-20 sm:pt-24 sm:pb-28 grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-brand-100 text-brand-700 px-3 py-1 text-xs font-semibold">
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span>
                    Incentive Compensation Management
                </span>
                <h1 class="mt-5 text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-slate-900 leading-[1.05]">
                    Sales commissions,<br>
                    <span class="bg-gradient-to-r from-brand-600 to-violet-600 bg-clip-text text-transparent">automated &amp; auditable.</span>
                </h1>
                <p class="mt-6 text-lg text-slate-600 max-w-xl">
                    Design incentive plans, credit every deal to the right rep, calculate payouts in one click,
                    and give your team a dashboard they trust — with a full audit trail behind every number.
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center px-6 py-3 rounded-xl bg-brand-600 text-white font-medium hover:bg-brand-700 shadow-sm">Open dashboard</a>
                    @else
                        <a href="{{ route('register') }}" class="inline-flex items-center px-6 py-3 rounded-xl bg-brand-600 text-white font-medium hover:bg-brand-700 shadow-sm">Start free →</a>
                        <a href="{{ route('login') }}" class="inline-flex items-center px-6 py-3 rounded-xl bg-white border border-slate-200 text-slate-700 font-medium hover:bg-slate-50">Log in</a>
                    @endauth
                </div>
                <div class="mt-8 flex items-center gap-6 text-sm text-slate-500">
                    <div class="flex items-center gap-2"><svg class="w-5 h-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg> No spreadsheets</div>
                    <div class="flex items-center gap-2"><svg class="w-5 h-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg> Fully auditable</div>
                </div>
            </div>

            {{-- Hero mock card --}}
            <div class="relative">
                <div class="rounded-2xl border border-slate-200 bg-white shadow-xl p-5">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-semibold text-slate-800">Q3 Field Sales — payout</div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-medium">Released</span>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <div class="rounded-xl bg-slate-50 p-3"><div class="text-xs text-slate-500">Attainment</div><div class="text-lg font-bold text-slate-900">128%</div></div>
                        <div class="rounded-xl bg-slate-50 p-3"><div class="text-xs text-slate-500">Credited</div><div class="text-lg font-bold text-slate-900">$1.2M</div></div>
                        <div class="rounded-xl bg-brand-50 p-3"><div class="text-xs text-brand-600">Commission</div><div class="text-lg font-bold text-brand-700">$84,300</div></div>
                    </div>
                    <div class="mt-4 space-y-2">
                        @foreach ([['Alice Chen','$21,400','92%'],['Marcus Lee','$18,750','78%'],['Priya Nair','$16,900','71%']] as $row)
                            <div class="flex items-center gap-3">
                                <span class="h-8 w-8 rounded-full bg-gradient-to-br from-brand-400 to-violet-500 text-white text-xs font-semibold inline-flex items-center justify-center">{{ substr($row[0],0,1) }}</span>
                                <div class="flex-1">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="font-medium text-slate-700">{{ $row[0] }}</span>
                                        <span class="font-semibold text-slate-900">{{ $row[1] }}</span>
                                    </div>
                                    <div class="mt-1 h-1.5 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-gradient-to-r from-brand-500 to-violet-500" style="width: {{ $row[2] }}"></div></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="absolute -bottom-5 -left-5 rounded-xl bg-slate-900 text-white px-4 py-3 shadow-lg hidden sm:block">
                    <div class="text-xs text-slate-300">Calculation</div>
                    <div class="text-sm font-semibold">✓ Completed · fully logged</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Features ===== --}}
    <section id="features" class="py-20 sm:py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900">Everything you need to run incentives</h2>
                <p class="mt-3 text-slate-600">From plan design to payout — one connected pipeline, not a maze of spreadsheets.</p>
            </div>
            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @php
                    $features = [
                        ['Plan builder','Quotas, cumulative &amp; non-cumulative tiers, caps, and custom formulas — no code.','M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.6L19 9.4V19a2 2 0 01-2 2z'],
                        ['Smart crediting','Alias-match every transaction to the right rep — with deal splits and manager overrides.','M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z'],
                        ['One-click calculations','Queued engine snapshots the plan, credits deals, computes payouts — reproducibly.','M13 10V3L4 14h7v7l9-11h-7z'],
                        ['Review &amp; release','Two-stage pipeline — nothing reaches reps until you review and release it.','M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['Rep dashboards','Every rep sees credits, payouts and statements — and can raise a dispute.','M3 13a2 2 0 012-2h2v9H5a2 2 0 01-2-2v-5zm7-4h2v13h-2V9zm7-3h2v16h-2V6z'],
                        ['Reports &amp; API','Payout &amp; crediting reports, CSV export, a REST API and BI feed for Power BI / Tableau.','M4 6h16M4 12h16M4 18h7'],
                    ];
                @endphp
                @foreach ($features as $f)
                    <div class="group rounded-2xl border border-slate-200 bg-white p-6 hover:shadow-card-hover transition">
                        <div class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 group-hover:bg-brand-600 group-hover:text-white transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $f[2] }}"/></svg>
                        </div>
                        <h3 class="mt-4 font-semibold text-slate-900">{!! $f[0] !!}</h3>
                        <p class="mt-1.5 text-sm text-slate-600">{!! $f[1] !!}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== How it works ===== --}}
    <section id="how" class="py-20 sm:py-24 bg-slate-50 border-y border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold tracking-tight text-slate-900 text-center">From raw sales to paid commissions in five steps</h2>
            <div class="mt-14 grid gap-8 md:grid-cols-5">
                @php
                    $steps = [
                        ['Design','Build the plan — tiers, rewards, quotas.'],
                        ['Import','Upload a CSV or push deals via the API.'],
                        ['Credit','Aliases assign each deal to a rep.'],
                        ['Calculate','Run the engine — every step logged.'],
                        ['Release','Review, release, and reps get paid.'],
                    ];
                @endphp
                @foreach ($steps as $i => $s)
                    <div class="relative">
                        <div class="h-10 w-10 rounded-full bg-brand-600 text-white font-bold inline-flex items-center justify-center">{{ $i + 1 }}</div>
                        <h3 class="mt-4 font-semibold text-slate-900">{{ $s[0] }}</h3>
                        <p class="mt-1 text-sm text-slate-600">{{ $s[1] }}</p>
                        @if (! $loop->last)
                            <div class="hidden md:block absolute top-5 left-12 right-0 h-px bg-slate-200"></div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== Platform / trust ===== --}}
    <section id="stack" class="py-20 sm:py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <h2 class="text-3xl font-bold tracking-tight text-slate-900">Built for finance-grade trust</h2>
                <p class="mt-4 text-slate-600">
                    Every calculation is reproducible from an immutable plan snapshot, and every rule application is
                    logged — so a dispute is a lookup, not an investigation. Multi-company, role-based access, and
                    multi-currency come standard.
                </p>
                <ul class="mt-6 space-y-3 text-sm">
                    @foreach (['Immutable plan-version snapshots','Full per-transaction audit trail','Multi-currency with effective-dated FX','Four roles with per-plan access control','REST API, webhooks &amp; BI export'] as $point)
                        <li class="flex items-start gap-3">
                            <svg class="w-5 h-5 text-brand-600 mt-0.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.1 3.1 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
                            <span class="text-slate-700">{!! $point !!}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="rounded-2xl bg-slate-900 p-8 text-slate-200 shadow-xl">
                <div class="text-xs uppercase tracking-wide text-slate-400">Audit trail</div>
                <div class="mt-3 space-y-2 font-mono text-xs">
                    <div class="flex justify-between"><span class="text-emerald-400">credit_matched</span><span>D-1042 → Alice · $6,000</span></div>
                    <div class="flex justify-between"><span class="text-emerald-400">fx_converted</span><span>EUR→USD @ 1.08</span></div>
                    <div class="flex justify-between"><span class="text-brand-300">tier_applied</span><span>attainment 15,000 · $900</span></div>
                    <div class="flex justify-between"><span class="text-amber-300">cap_applied</span><span>capped at $800</span></div>
                    <div class="flex justify-between"><span class="text-violet-300">override_applied</span><span>manager 5% · $1,000</span></div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== CTA ===== --}}
    <section class="pb-24">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl bg-gradient-to-br from-brand-600 to-violet-600 px-8 py-14 text-center text-white shadow-xl">
                <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight">Ready to retire the commission spreadsheet?</h2>
                <p class="mt-3 text-brand-100 max-w-xl mx-auto">Spin up a workspace in seconds. Design a plan, import a deal, run a calculation — see the whole pipeline work.</p>
                <div class="mt-8 flex justify-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center px-6 py-3 rounded-xl bg-white text-brand-700 font-semibold hover:bg-brand-50">Open your dashboard</a>
                    @else
                        <a href="{{ route('register') }}" class="inline-flex items-center px-6 py-3 rounded-xl bg-white text-brand-700 font-semibold hover:bg-brand-50">Create your workspace</a>
                        <a href="{{ route('login') }}" class="inline-flex items-center px-6 py-3 rounded-xl bg-brand-500/30 border border-white/30 text-white font-semibold hover:bg-brand-500/50">Log in</a>
                    @endauth
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Footer ===== --}}
    <footer class="border-t border-slate-100 py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-slate-500">
            <div class="flex items-center gap-2.5">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-violet-600 text-white font-bold">₡</span>
                <span class="font-semibold text-slate-700">{{ $brand }}</span>
            </div>
            <div>&copy; {{ date('Y') }} {{ $brand }}. Sales commission &amp; incentive management.</div>
        </div>
    </footer>

</body>
</html>
