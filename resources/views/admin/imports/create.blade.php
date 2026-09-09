<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Import transactions (CSV)') }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white shadow-sm sm:rounded-lg p-6">
            <x-input-error :messages="$errors->get('file')" class="mb-4" />

            <form method="POST" action="{{ route('admin.imports.preview') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="file" value="CSV file" />
                    <input id="file" name="file" type="file" accept=".csv,text/csv"
                           class="block mt-1 w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700" required />
                    <p class="mt-2 text-xs text-gray-500">
                        Up to 10&nbsp;MB. The next step lets you confirm how columns map to transaction fields.
                        Re-importing the same file is safe — rows are matched on their external ID and updated, never duplicated.
                    </p>
                </div>
                <div class="flex justify-end">
                    <x-primary-button>Continue</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
