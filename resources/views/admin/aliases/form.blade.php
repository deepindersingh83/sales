@php
    $isEdit = $alias->exists;
    $action = $isEdit ? route('admin.aliases.update', $alias) : route('admin.aliases.store');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $isEdit ? 'Edit alias' : 'New alias' }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <form method="POST" action="{{ $action }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div>
                <x-input-label for="user_id" value="Credit to" />
                <select id="user_id" name="user_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                    <option value="">— select member —</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}" @selected(old('user_id', $alias->user_id) == $member->id)>
                            {{ $member->name }} ({{ $member->email }})
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="match_field" value="Match field (transaction column / raw_data key)" />
                <x-text-input id="match_field" name="match_field" class="block mt-1 w-full" :value="old('match_field', $alias->match_field)" required />
                <p class="mt-1 text-xs text-gray-500">e.g. <code>rep</code>, <code>owner</code>, <code>sales_rep</code>.</p>
                <x-input-error :messages="$errors->get('match_field')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="alias_value" value="Match value (keyword)" />
                <x-text-input id="alias_value" name="alias_value" class="block mt-1 w-full" :value="old('alias_value', $alias->alias_value)" required />
                <x-input-error :messages="$errors->get('alias_value')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="match_type" value="Match type" />
                <select id="match_type" name="match_type" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                    <option value="exact" @selected(old('match_type', $alias->match_type) === 'exact')>Exact match</option>
                    <option value="contains" @selected(old('match_type', $alias->match_type) === 'contains')>Contains</option>
                </select>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.aliases.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                <x-primary-button>{{ $isEdit ? 'Save' : 'Create alias' }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
