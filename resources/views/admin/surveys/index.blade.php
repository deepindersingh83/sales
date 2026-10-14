<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Rep surveys" subtitle="Ask reps for feedback" />
    </x-slot>
    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl space-y-6">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <x-ui.card>
            <form method="POST" action="{{ route('admin.surveys.store') }}" class="flex items-end gap-3">
                @csrf
                <div class="flex-1"><x-input-label for="question" value="Question" /><x-text-input id="question" name="question" class="mt-1 block w-full" placeholder="How clear is your commission plan?" required /></div>
                <x-ui.button>Create survey</x-ui.button>
            </form>
        </x-ui.card>

        @forelse ($surveys as $survey)
            <x-ui.card>
                <div class="flex items-center justify-between">
                    <div class="font-medium text-slate-800">{{ $survey->question }}</div>
                    <form method="POST" action="{{ route('admin.surveys.destroy', $survey) }}" onsubmit="return confirm('Delete?')">
                        @csrf @method('DELETE')<button class="text-rose-600 hover:text-rose-800 text-sm">Delete</button>
                    </form>
                </div>
                <div class="mt-1 text-xs text-slate-400">{{ $survey->responses_count }} responses
                    @if ($survey->responses_count) · avg rating {{ number_format($survey->responses->avg('rating'), 1) }}/5 @endif
                </div>
                @if ($survey->responses->isNotEmpty())
                    <div class="mt-3 space-y-2">
                        @foreach ($survey->responses as $r)
                            <div class="text-sm border-t border-slate-100 pt-2">
                                <span class="font-medium text-slate-700">{{ $r->user?->name }}</span>
                                <span class="text-amber-500">{{ str_repeat('★', (int) $r->rating) }}{{ str_repeat('☆', 5 - (int) $r->rating) }}</span>
                                @if ($r->comment)<p class="text-slate-500">{{ $r->comment }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        @empty
            <x-ui.card><p class="text-sm text-slate-500">No surveys yet.</p></x-ui.card>
        @endforelse
    </div>
</x-app-layout>
