<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $plan->name }}</h2>
            <div class="flex items-center gap-3">
                @can('createForPlan', [App\Models\CalcRun::class, $plan])
                    <form method="POST" action="{{ route('admin.plans.simulate', $plan) }}">
                        @csrf
                        <button class="inline-flex items-center px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-md hover:bg-slate-50">
                            Simulate
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.plans.calc-runs.store', $plan) }}">
                        @csrf
                        <button class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">
                            Run calculation
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.plans.true-up', $plan) }}" onsubmit="return confirm('Run a true-up? This pays only the delta vs already-released commission.')">
                        @csrf
                        <button class="inline-flex items-center px-4 py-2 bg-white border border-slate-300 text-slate-700 text-sm font-medium rounded-md hover:bg-slate-50">
                            True-up
                        </button>
                    </form>
                @endcan
                @can('manageAccess', $plan)
                    <a href="{{ route('admin.plans.access.edit', $plan) }}" class="text-sm text-slate-600 hover:text-slate-900">Manage access</a>
                @endcan
                @can('update', $plan)
                    <a href="{{ route('admin.plans.edit', $plan) }}" class="text-sm text-indigo-600 hover:text-indigo-900">Edit</a>
                @endcan
                @can('delete', $plan)
                    <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}"
                          onsubmit="return confirm('Delete this plan?')">
                        @csrf @method('DELETE')
                        <button class="text-sm text-red-600 hover:text-red-900">Delete</button>
                    </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div><dt class="text-gray-500">Status</dt><dd class="font-medium">{{ $plan->status->label() }}</dd></div>
                <div><dt class="text-gray-500">Period</dt><dd class="font-medium capitalize">{{ $plan->period_type }}</dd></div>
                <div><dt class="text-gray-500">Metric</dt><dd class="font-medium capitalize">{{ $plan->performance_metric }}</dd></div>
                <div><dt class="text-gray-500">Currency</dt><dd class="font-medium">{{ $plan->currency }}</dd></div>
                @if ($plan->quota !== null)<div><dt class="text-gray-500">Quota</dt><dd class="font-medium">{{ number_format((float) $plan->quota, 2) }}</dd></div>@endif
                @if ($plan->payout_cap !== null)<div><dt class="text-gray-500">Payout cap</dt><dd class="font-medium">{{ number_format((float) $plan->payout_cap, 2) }}</dd></div>@endif
            </dl>
            @if ($plan->commission_formula)
                <div class="mt-4 text-sm">
                    <span class="text-gray-500">Formula:</span>
                    <code class="ml-1 px-2 py-0.5 bg-gray-100 rounded">{{ $plan->commission_formula }}</code>
                    <span class="text-gray-400">(replaces tier math)</span>
                </div>
            @endif
            @if ($plan->description)
                <p class="mt-4 text-sm text-gray-600">{{ $plan->description }}</p>
            @endif
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-3">Commission tiers</h3>
            @forelse ($plan->tiers as $tier)
                <div class="flex justify-between text-sm border-b border-gray-100 py-2">
                    <span>{{ number_format($tier->threshold_from, 2) }} – {{ $tier->threshold_to !== null ? number_format($tier->threshold_to, 2) : '∞' }}</span>
                    <span>
                        {{ $tier->kind === 'rate' ? rtrim(rtrim(number_format($tier->rate_or_amount * 100, 4), '0'), '.').'%' : number_format($tier->rate_or_amount, 2) }}
                        <span class="text-gray-400 ml-2">{{ $tier->is_cumulative ? 'cumulative' : 'flat' }}</span>
                    </span>
                </div>
            @empty
                <p class="text-sm text-gray-500">No tiers.</p>
            @endforelse
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-3">Reward rules</h3>
            @forelse ($plan->rewardRules as $rule)
                <div class="flex justify-between text-sm border-b border-gray-100 py-2">
                    <span>{{ $rule->reward_type->label() }}{{ isset($rule->meta['label']) ? ' — '.$rule->meta['label'] : '' }}</span>
                    <span>{{ $rule->value !== null ? number_format($rule->value, 4) : '—' }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-500">No reward rules.</p>
            @endforelse
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-3">Plan versions (calculation snapshots)</h3>
            @forelse ($plan->versions->sortByDesc('version_number') as $version)
                <div class="text-sm border-b border-gray-100 py-2">v{{ $version->version_number }} — {{ $version->created_at->diffForHumans() }}</div>
            @empty
                <p class="text-sm text-gray-500">No versions yet — a snapshot is taken each time the plan is calculated.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
