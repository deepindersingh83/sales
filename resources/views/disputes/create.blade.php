<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Raise a dispute') }}</h2>
    </x-slot>

    <div class="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('disputes.store') }}" class="bg-white shadow-sm sm:rounded-lg p-6 space-y-4">
            @csrf

            <div>
                <x-input-label for="category" value="Category" />
                <select id="category" name="category" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" required>
                    @foreach (['Missing credit', 'Incorrect amount', 'Wrong plan', 'Payout question', 'Other'] as $cat)
                        <option value="{{ $cat }}" @selected(old('category') === $cat)>{{ $cat }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('category')" class="mt-2" />
            </div>

            @if ($transactions->isNotEmpty())
                <div>
                    <x-input-label for="transaction_id" value="Related transaction (optional)" />
                    <select id="transaction_id" name="transaction_id" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">— none —</option>
                        @foreach ($transactions as $tx)
                            <option value="{{ $tx->id }}" @selected(old('transaction_id') == $tx->id)>
                                {{ $tx->external_id }} — {{ $tx->currency }} {{ number_format((float) $tx->amount, 2) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <x-input-label for="description" value="Describe the issue" />
                <textarea id="description" name="description" rows="4"
                          class="block mt-1 w-full border-gray-300 rounded-md shadow-sm" required>{{ old('description') }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('disputes.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
                <x-primary-button>Submit dispute</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
