@php $isEdit = $announcement->exists; @endphp
<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header :title="$isEdit ? 'Edit announcement' : 'New announcement'" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-2xl">
        <x-ui.card>
            <form method="POST" action="{{ $isEdit ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}" class="space-y-4">
                @csrf
                @if ($isEdit) @method('PUT') @endif
                <div>
                    <x-input-label for="title" value="Title" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" :value="old('title', $announcement->title)" required />
                    <x-input-error :messages="$errors->get('title')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="body" value="Message" />
                    <textarea id="body" name="body" rows="5" class="mt-1 block w-full border-slate-300 rounded-lg shadow-sm" required>{{ old('body', $announcement->body) }}</textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-2" />
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="publish" value="1" @checked(old('publish', $announcement->published_at !== null)) class="rounded border-slate-300 text-brand-600">
                    <span class="text-slate-700">Publish now (visible to all reps)</span>
                </label>
                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('admin.announcements.index') }}" class="text-sm text-slate-600 hover:text-slate-900">Cancel</a>
                    <x-ui.button>{{ $isEdit ? 'Save' : 'Create' }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
