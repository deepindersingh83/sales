<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Recurring imports" subtitle="Scheduled transaction feeds re-imported automatically" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl space-y-6">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        <x-input-error :messages="$errors->get('run')" />

        <x-ui.card>
            <h2 class="text-sm font-semibold text-slate-800 mb-3">New recurring source</h2>
            <form method="POST" action="{{ route('admin.import-sources.store') }}" enctype="multipart/form-data" class="grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
                @csrf
                <div class="sm:col-span-2">
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="schedule" value="Cadence" />
                    <select id="schedule" name="schedule" class="mt-1 block w-full border-slate-300 rounded-lg shadow-sm text-sm" required>
                        <option value="hourly">Hourly</option>
                        <option value="daily" selected>Daily</option>
                        <option value="weekly">Weekly</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="file" value="CSV file" />
                    <input id="file" name="file" type="file" accept=".csv,text/csv" required
                        class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-brand-700 hover:file:bg-brand-100" />
                    <x-input-error :messages="$errors->get('file')" class="mt-1" />
                </div>
                <div class="sm:col-span-6 flex justify-end">
                    <x-ui.button>Create source</x-ui.button>
                </div>
            </form>
            <p class="mt-3 text-xs text-slate-500">Column mapping is auto-detected from the header row and reused on every run. Re-imports are idempotent (matched on external ID).</p>
        </x-ui.card>

        <x-ui.card padding="p-0">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-3">Name</th><th class="px-6 py-3">Cadence</th>
                        <th class="px-6 py-3">Last synced</th><th class="px-6 py-3">Next run</th><th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sources as $source)
                        <tr>
                            <td class="px-6 py-3 text-slate-800">{{ $source->name }}</td>
                            <td class="px-6 py-3">
                                <span class="inline-block rounded-full bg-slate-100 text-slate-600 px-2 py-0.5 text-xs capitalize">{{ $source->schedule ?? 'manual' }}</span>
                            </td>
                            <td class="px-6 py-3 text-slate-500">{{ $source->last_synced_at?->diffForHumans() ?? 'never' }}</td>
                            <td class="px-6 py-3 text-slate-500">{{ $source->next_run_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-6 py-3 text-right space-x-3">
                                @if ($source->schedule && $source->source_path)
                                    <form method="POST" action="{{ route('admin.import-sources.run', $source) }}" class="inline">
                                        @csrf
                                        <button class="text-brand-600 hover:text-brand-800">Run now</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.import-sources.destroy', $source) }}" class="inline" onsubmit="return confirm('Delete this source?')">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-600 hover:text-rose-800">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-8 text-center text-slate-500">No recurring sources yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.card>
    </div>
</x-app-layout>
