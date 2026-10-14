<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dispute #{{ $dispute->id }} — {{ $dispute->category }}</h2>
            <x-dispute-status :status="$dispute->status" />
        </div>
    </x-slot>

    <div class="py-8 max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session('status'))
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-3">
            <div class="text-sm text-gray-500">
                Raised by {{ $dispute->user?->name }} · {{ $dispute->created_at->diffForHumans() }}
                @if ($dispute->transaction) · transaction <span class="font-mono">{{ $dispute->transaction->external_id }}</span> @endif
            </div>
            <p class="text-gray-800 whitespace-pre-line">{{ $dispute->description }}</p>

            @if ($dispute->status?->value === 'resolved' && $dispute->resolution_notes)
                <div class="mt-2 rounded-md bg-green-50 p-3 text-sm text-green-800">
                    <strong>Resolution:</strong> {{ $dispute->resolution_notes }}
                </div>
            @endif
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-3">Comments</h3>
            <div class="space-y-3">
                @forelse ($dispute->comments as $comment)
                    <div class="border-b border-gray-100 pb-3">
                        <div class="text-xs text-gray-500">{{ $comment->user?->name }} · {{ $comment->created_at->diffForHumans() }}</div>
                        <p class="text-sm text-gray-800 whitespace-pre-line">{{ $comment->comment }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No comments yet.</p>
                @endforelse
            </div>

            @can('comment', $dispute)
                <form method="POST" action="{{ route('disputes.comments.store', $dispute) }}" class="mt-4 space-y-2">
                    @csrf
                    <textarea name="comment" rows="2" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Add a comment…" required></textarea>
                    <x-input-error :messages="$errors->get('comment')" />
                    <div class="text-right"><x-primary-button>Comment</x-primary-button></div>
                </form>
            @endcan
        </div>

        @can('resolve', $dispute)
            @if ($dispute->status?->value !== 'resolved')
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Resolve</h3>
                    <form method="POST" action="{{ route('disputes.resolve', $dispute) }}" class="space-y-2">
                        @csrf @method('PATCH')
                        <textarea name="resolution_notes" rows="2" class="block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Resolution notes (optional)…"></textarea>
                        <div class="text-right">
                            <button class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">Mark resolved</button>
                        </div>
                    </form>
                </div>
            @endif
        @endcan
    </div>
</x-app-layout>
