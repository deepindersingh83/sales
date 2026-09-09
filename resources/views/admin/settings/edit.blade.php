<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Workspace settings" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-lg">
        @if (session('status'))
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        <x-ui.card>
            <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-4">
                @csrf @method('PUT')
                <div>
                    <x-input-label for="name" value="Company / workspace name" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $workspace->name)" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="base_currency" value="Base currency" />
                    <x-text-input id="base_currency" name="base_currency" maxlength="3" class="mt-1 block w-full uppercase" :value="old('base_currency', $workspace->base_currency)" required />
                    <x-input-error :messages="$errors->get('base_currency')" class="mt-2" />
                </div>
                <div class="flex justify-end">
                    <x-ui.button>Save settings</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
