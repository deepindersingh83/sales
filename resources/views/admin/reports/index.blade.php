<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Reports') }}</h2>
    </x-slot>

    <div class="py-8 max-w-4xl mx-auto sm:px-6 lg:px-8">
        @unless ($hasData)
            <div class="mb-4 rounded-md bg-yellow-50 p-4 text-sm text-yellow-800">
                No released data yet. Reports summarise released credits and rewards — run a
                calculation and release its results first.
            </div>
        @endunless

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <a href="{{ route('admin.reports.overview') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:bg-gray-50 ring-1 ring-brand-100">
                <div class="text-lg font-medium text-brand-700">📊 Analytics overview</div>
                <p class="mt-1 text-sm text-gray-500">Charts: top reps, payout by type &amp; month, liability.</p>
            </a>
            <a href="{{ route('admin.reports.attainment') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:bg-gray-50">
                <div class="text-lg font-medium text-gray-900">Attainment by user</div>
                <p class="mt-1 text-sm text-gray-500">Credited amount &amp; payout per rep.</p>
            </a>
            <a href="{{ route('admin.reports.liability') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:bg-gray-50">
                <div class="text-lg font-medium text-gray-900">Commission liability</div>
                <p class="mt-1 text-sm text-gray-500">Earned but not yet released, by rep.</p>
            </a>
            <a href="{{ route('admin.reports.payout-by-user') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:bg-gray-50">
                <div class="text-lg font-medium text-gray-900">Payout by user</div>
                <p class="mt-1 text-sm text-gray-500">Total released payout per rep.</p>
            </a>
            <a href="{{ route('admin.reports.payout-by-plan') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:bg-gray-50">
                <div class="text-lg font-medium text-gray-900">Payout by plan</div>
                <p class="mt-1 text-sm text-gray-500">Total released payout per plan.</p>
            </a>
            <a href="{{ route('admin.reports.crediting') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:bg-gray-50">
                <div class="text-lg font-medium text-gray-900">Crediting by product/customer</div>
                <p class="mt-1 text-sm text-gray-500">Released credited amount grouped by a transaction field.</p>
            </a>
            <a href="{{ route('admin.reports.double-payments') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:bg-gray-50">
                <div class="text-lg font-medium text-gray-900">Double-payment detection</div>
                <p class="mt-1 text-sm text-gray-500">Transactions with matching amount + date — possible duplicates.</p>
            </a>
            <a href="{{ route('admin.reports.asc606') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:bg-gray-50">
                <div class="text-lg font-medium text-gray-900">ASC 606 amortization</div>
                <p class="mt-1 text-sm text-gray-500">Straight-line recognition of released commissions over time.</p>
            </a>
        </div>
    </div>
</x-app-layout>
