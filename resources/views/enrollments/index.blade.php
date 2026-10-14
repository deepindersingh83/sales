<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Plans & terms" subtitle="Review and sign your incentive plans" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        @forelse ($plans as $plan)
            @php $enrollment = $mine->get($plan->id); $terms = $plan->terms->first(); @endphp
            <x-ui.card>
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium text-slate-800">{{ $plan->name }}</div>
                        <div class="text-xs text-slate-400 capitalize">{{ $plan->period_type }} · {{ $plan->performance_metric }}</div>
                    </div>
                    @if ($enrollment && $enrollment->signed_at)
                        <x-ui.badge color="green">Signed {{ $enrollment->signed_at->toDateString() }}</x-ui.badge>
                    @else
                        <x-ui.badge color="amber">Not signed</x-ui.badge>
                    @endif
                </div>

                @if ($terms)
                    <div class="mt-3 max-h-40 overflow-y-auto rounded-lg bg-slate-50 border border-slate-100 p-3 text-xs text-slate-600 whitespace-pre-line">{{ $terms->body }}</div>
                @endif

                @unless ($enrollment && $enrollment->signed_at)
                    <form method="POST" action="{{ route('enrollments.sign', $plan) }}" class="mt-3 flex items-end gap-2">
                        @csrf
                        <div class="flex-1">
                            <x-input-label :for="'sig_'.$plan->id" value="Type your full name to sign" />
                            <x-text-input :id="'sig_'.$plan->id" name="signature" class="mt-1 block w-full" required />
                        </div>
                        <x-ui.button>Sign &amp; enroll</x-ui.button>
                    </form>
                @endunless
            </x-ui.card>
        @empty
            <x-ui.card><p class="text-sm text-slate-500">No active plans to enroll in yet.</p></x-ui.card>
        @endforelse
    </div>
</x-app-layout>
