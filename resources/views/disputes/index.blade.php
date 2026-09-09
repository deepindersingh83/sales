<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('My disputes') }}</h2>
            <a href="{{ route('disputes.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">New dispute</a>
        </div>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        {{-- Claim a transaction --}}
        <div class="mb-4 bg-white shadow-sm rounded-lg p-4">
            <form method="POST" action="{{ route('disputes.claim') }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <div class="flex-1 min-w-[10rem]">
                    <label class="block text-xs text-gray-500">Claim a transaction (by external ID)</label>
                    <input name="external_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="e.g. D-1042" required />
                </div>
                <div class="flex-1 min-w-[10rem]">
                    <label class="block text-xs text-gray-500">Note (optional)</label>
                    <input name="note" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm" />
                </div>
                <button class="px-4 py-2 bg-slate-700 text-white text-sm font-medium rounded-md hover:bg-slate-800">Claim</button>
            </form>
        </div>

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            @forelse ($disputes as $dispute)
                <a href="{{ route('disputes.show', $dispute) }}" class="block px-6 py-4 border-b border-gray-100 hover:bg-gray-50">
                    <div class="flex justify-between">
                        <span class="font-medium text-gray-900">{{ $dispute->category }}</span>
                        <x-dispute-status :status="$dispute->status" />
                    </div>
                    <p class="mt-1 text-sm text-gray-500 truncate">{{ $dispute->description }}</p>
                </a>
            @empty
                <p class="p-6 text-gray-500">No disputes yet.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
