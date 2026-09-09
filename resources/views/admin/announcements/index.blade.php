<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Announcements" subtitle="Broadcast news to your reps">
            <x-slot name="actions">
                @can('create', App\Models\Announcement::class)
                    <x-ui.button href="{{ route('admin.announcements.create') }}">New announcement</x-ui.button>
                @endcan
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl space-y-4">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <x-ui.card padding="p-0">
            @forelse ($announcements as $a)
                <div class="flex items-start justify-between gap-4 px-6 py-4 border-b border-slate-100">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-slate-800">{{ $a->title }}</span>
                            @if ($a->published_at)
                                <x-ui.badge color="green">Published</x-ui.badge>
                            @else
                                <x-ui.badge>Draft</x-ui.badge>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-slate-500 line-clamp-2">{{ $a->body }}</p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0 text-sm">
                        @can('update', $a)<a href="{{ route('admin.announcements.edit', $a) }}" class="text-brand-600 hover:text-brand-700">Edit</a>@endcan
                        @can('delete', $a)
                            <form method="POST" action="{{ route('admin.announcements.destroy', $a) }}" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="text-rose-600 hover:text-rose-800">Delete</button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="px-6 py-8 text-sm text-slate-500 text-center">No announcements yet.</p>
            @endforelse
        </x-ui.card>
    </div>
</x-app-layout>
