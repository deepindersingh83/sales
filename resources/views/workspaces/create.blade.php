<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="New company" subtitle="Create another workspace you own" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-lg">
        <x-ui.card>
            <form method="POST" action="{{ route('workspaces.store') }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="name" value="Company / workspace name" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="base_currency" value="Base currency" />
                    <x-text-input id="base_currency" name="base_currency" maxlength="3" class="mt-1 block w-full uppercase" :value="old('base_currency', 'USD')" />
                </div>
                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('dashboard') }}" class="text-sm text-slate-600 hover:text-slate-900">Cancel</a>
                    <x-ui.button>Create company</x-ui.button>
                </div>
                <p class="text-xs text-slate-400">You'll become the Full Admin of the new company and switch to it immediately.</p>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
