<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Calculation run #{{ $run->id }}</h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.calc-runs.logs', $run) }}" class="text-sm text-slate-600 hover:text-slate-900">Audit trail</a>
                <a href="{{ route('admin.plans.show', $run->plan) }}" class="text-sm text-indigo-600 hover:text-indigo-900">{{ $run->plan->name }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6"
         x-data="calcStatus('{{ route('admin.calc-runs.status', $run) }}', @js($run->status->value), @js($run->status->isTerminal()))"
         x-init="init()">

        @if (session('status'))
            <div class="rounded-md bg-blue-50 p-4 text-sm text-blue-800">{{ session('status') }}</div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-500">Status:</span>
                <span class="inline-flex px-2 text-xs font-semibold rounded-full"
                      :class="{
                        'bg-green-100 text-green-800': status === 'completed',
                        'bg-blue-100 text-blue-800': status === 'running',
                        'bg-gray-100 text-gray-800': status === 'queued',
                        'bg-red-100 text-red-800': status === 'failed',
                      }"
                      x-text="status.charAt(0).toUpperCase() + status.slice(1)"></span>
                <span x-show="!isTerminal" class="text-xs text-gray-400">refreshing…</span>
            </div>

            <template x-if="error">
                <p class="mt-3 text-sm text-red-700" x-text="error"></p>
            </template>

            <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div><dt class="text-gray-500">Plan version</dt><dd class="font-medium">v{{ $run->planVersion?->version_number ?? '—' }}</dd></div>
                <div><dt class="text-gray-500">Credits</dt><dd class="font-medium" x-text="credits">{{ $creditsCount }}</dd></div>
                <div><dt class="text-gray-500">Rewards</dt><dd class="font-medium" x-text="rewards">{{ $rewardsCount }}</dd></div>
                <div><dt class="text-gray-500">Log entries</dt><dd class="font-medium">{{ $logsCount }}</dd></div>
            </dl>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-sm font-medium text-gray-900 mb-2">Review &amp; release</h3>
            <p class="text-sm text-gray-600">
                Credits and rewards start as <strong>pending</strong> and are hidden from reps until released.
                Once this run completes, use the review screens to move them pending → reviewed → released.
            </p>
            @if (Route::has('admin.calc-runs.credits.index'))
                <div class="mt-3 flex gap-4 text-sm" x-show="status === 'completed'">
                    <a href="{{ route('admin.calc-runs.credits.index', $run) }}" class="text-indigo-600 hover:text-indigo-900">Review credits →</a>
                    <a href="{{ route('admin.calc-runs.rewards.index', $run) }}" class="text-indigo-600 hover:text-indigo-900">Review rewards →</a>
                </div>
            @endif
        </div>
    </div>

    <script>
        function calcStatus(url, initialStatus, initialTerminal) {
            return {
                status: initialStatus,
                isTerminal: initialTerminal,
                credits: {{ $creditsCount }},
                rewards: {{ $rewardsCount }},
                error: @js($run->error),
                timer: null,
                init() {
                    if (!this.isTerminal) this.poll();
                },
                poll() {
                    this.timer = setInterval(async () => {
                        try {
                            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                            const data = await res.json();
                            this.status = data.status;
                            this.credits = data.credits;
                            this.rewards = data.rewards;
                            this.error = data.error;
                            this.isTerminal = data.is_terminal;
                            if (data.is_terminal) {
                                clearInterval(this.timer);
                                // Reload once so server-rendered sections reflect final counts.
                                window.location.reload();
                            }
                        } catch (e) { /* keep polling */ }
                    }, 2000);
                },
            };
        }
    </script>
</x-app-layout>
