{{-- Shared header: pipeline counts + bulk action buttons. Expects $counts, $transitionRoute. --}}
<div class="bg-white shadow-sm sm:rounded-lg p-6">
    <div class="flex flex-wrap items-center gap-6 text-sm">
        <div><span class="text-gray-500">Pending</span> <span class="font-semibold">{{ $counts['pending'] }}</span></div>
        <div><span class="text-gray-500">Reviewed</span> <span class="font-semibold">{{ $counts['reviewed'] }}</span></div>
        <div><span class="text-gray-500">Released</span> <span class="font-semibold text-green-700">{{ $counts['released'] }}</span></div>

        @can('release', $run)
            <div class="ml-auto flex gap-2">
                <form method="POST" action="{{ $transitionRoute }}">
                    @csrf
                    <input type="hidden" name="action" value="review" />
                    <button class="px-3 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
                            @disabled($counts['pending'] === 0)>Review all pending</button>
                </form>
                <form method="POST" action="{{ $transitionRoute }}">
                    @csrf
                    <input type="hidden" name="action" value="release" />
                    <button class="px-3 py-2 text-sm bg-green-600 text-white rounded-md hover:bg-green-700"
                            @disabled($counts['reviewed'] === 0)>Release all reviewed</button>
                </form>
            </div>
        @endcan
    </div>
    <p class="mt-3 text-xs text-gray-500">
        Reps see nothing until it is <strong>released</strong>. Pending → Reviewed → Released.
    </p>
</div>
