<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Xero" subtitle="Import sales invoices as transactions">
            <x-slot name="actions">
                <x-ui.button variant="ghost" href="{{ route('admin.connectors.index') }}" class="hidden sm:inline-flex">← Integrations</x-ui.button>
                @if ($canManage && $configured)
                    <x-ui.button href="{{ route('admin.connectors.xero.connect') }}">
                        @if ($sources->isEmpty())
                            Connect Xero
                        @else
                            <span class="sm:hidden">Connect</span><span class="hidden sm:inline">Connect another organisation</span>
                        @endif
                    </x-ui.button>
                @endif
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-5xl space-y-6">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if (session('check_error'))
            <div class="rounded-lg bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800">{{ session('check_error') }}</div>
        @endif
        <x-input-error :messages="$errors->get('run')" />
        <x-input-error :messages="$errors->get('xero')" />

        {{-- Connected organisations --}}
        <x-ui.card padding="p-0">
            <div class="px-6 py-4 border-b border-slate-100">
                <h2 class="text-sm font-semibold text-slate-800">Connected organisations</h2>
            </div>

            @forelse ($sources as $source)
                @php
                    $config = $source->config ?? [];
                    $checkedAt = isset($config['last_checked_at']) ? \Illuminate\Support\Carbon::parse($config['last_checked_at']) : null;
                    $needsAttention = $source->last_error || (($config['last_check_ok'] ?? true) === false);
                    $disconnected = ! $source->isRunnable();
                @endphp
                <div class="px-6 py-5 border-b border-slate-100 last:border-0 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="font-medium text-slate-900">{{ $config['tenant_name'] ?? $source->name }}</div>
                            <div class="text-xs text-slate-500">
                                Last sync: {{ $source->last_synced_at?->diffForHumans() ?? 'never' }}
                                · Next: {{ $source->next_run_at?->diffForHumans() ?? ($source->schedule ? 'due now' : 'manual only') }}
                                @if ($checkedAt)
                                    · Checked {{ $checkedAt->diffForHumans() }}
                                @endif
                            </div>
                        </div>
                        @if ($disconnected)
                            <x-ui.badge color="slate">Disconnected</x-ui.badge>
                        @elseif ($needsAttention)
                            <x-ui.badge color="red">Needs attention</x-ui.badge>
                        @else
                            <x-ui.badge color="green">Connected</x-ui.badge>
                        @endif
                    </div>

                    @if ($source->last_error)
                        <p class="text-xs text-rose-600">Last sync failed: {{ $source->last_error }}</p>
                    @endif
                    @if (($config['last_check_ok'] ?? true) === false)
                        <p class="text-xs text-rose-600">Last check: {{ $config['last_check_message'] ?? 'failed' }}</p>
                    @endif

                    @if ($canManage)
                        <div class="flex flex-wrap items-center gap-2">
                            <form method="POST" action="{{ route('admin.connectors.xero.check', $source) }}">
                                @csrf
                                <x-ui.button variant="secondary">Check connection</x-ui.button>
                            </form>
                            @if (! $disconnected)
                                <form method="POST" action="{{ route('admin.import-sources.run', $source) }}">
                                    @csrf
                                    <x-ui.button variant="secondary">Sync now</x-ui.button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.connectors.xero.update', $source) }}" class="flex items-center gap-2">
                                @csrf @method('PUT')
                                <label for="schedule-{{ $source->id }}" class="text-sm text-slate-600">Sync</label>
                                <select id="schedule-{{ $source->id }}" name="schedule" class="border-slate-300 rounded-lg shadow-sm text-sm" onchange="this.form.submit()">
                                    @foreach ($schedules as $schedule)
                                        <option value="{{ $schedule }}" @selected(($source->schedule ?? 'manual') === $schedule)>{{ ucfirst($schedule) }}</option>
                                    @endforeach
                                </select>
                            </form>
                            @if ($configured)
                                <x-ui.button variant="ghost" href="{{ route('admin.connectors.xero.connect') }}">Reconnect</x-ui.button>
                            @endif
                            <form method="POST" action="{{ route('admin.connectors.xero.destroy', $source) }}" class="ml-auto"
                                  onsubmit="return confirm(@js('Disconnect '.($config['tenant_name'] ?? $source->name).'? Imported transactions are kept.'))">
                                @csrf @method('DELETE')
                                <button class="text-sm text-rose-600 hover:text-rose-800">Disconnect</button>
                            </form>
                        </div>
                    @endif
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-slate-500">
                    No Xero organisation connected yet.
                    @if ($canManage && ! $configured)
                        Add your Xero app keys below first.
                    @endif
                </div>
            @endforelse
        </x-ui.card>

        {{-- Xero app keys --}}
        <x-ui.card>
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-800">Xero app</h2>
                @if (! $configured)
                    <x-ui.badge color="amber">Not set up</x-ui.badge>
                @elseif ($app['source'] === 'workspace')
                    <x-ui.badge color="green">Using keys saved here</x-ui.badge>
                @else
                    <x-ui.badge color="blue">Using server keys (.env)</x-ui.badge>
                @endif
            </div>

            <ol class="mt-3 text-sm text-slate-600 list-decimal list-inside space-y-1">
                <li>Create a <span class="font-medium">Web app</span> at <a href="https://developer.xero.com/app/manage" target="_blank" rel="noopener" class="text-brand-600 hover:text-brand-800">developer.xero.com</a>.</li>
                <li>Set its redirect URI to <code class="text-xs bg-slate-100 rounded px-1.5 py-0.5 break-all select-all">{{ $redirectUri }}</code></li>
                <li>Paste the app's client id and secret below, then connect.</li>
            </ol>

            @if ($isFullAdmin)
                <form method="POST" action="{{ route('admin.connectors.xero.app.update') }}" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @csrf @method('PUT')
                    <div>
                        <x-input-label for="client_id" value="Client ID" />
                        <x-text-input id="client_id" name="client_id" class="mt-1 block w-full" :value="old('client_id', $savedClientId)" required autocomplete="off" />
                        <x-input-error :messages="$errors->get('client_id')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="client_secret" value="Client secret" />
                        <x-text-input id="client_secret" name="client_secret" type="password" class="mt-1 block w-full" autocomplete="new-password"
                            :placeholder="$hasSavedSecret ? '•••••••• saved — leave blank to keep' : ''" />
                        <x-input-error :messages="$errors->get('client_secret')" class="mt-1" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="scopes" value="Scopes (optional)" />
                        <x-text-input id="scopes" name="scopes" class="mt-1 block w-full text-sm" :value="old('scopes')"
                            :placeholder="$app['scopes']" />
                        <p class="mt-1 text-xs text-slate-500">Leave blank for the default. Apps created with Xero's newer granular scopes may need e.g. <code>accounting.invoices.read</code>.</p>
                    </div>
                    <div class="sm:col-span-2 flex items-center justify-between">
                        <x-ui.button>Save app keys</x-ui.button>
                    </div>
                </form>
                @if ($savedClientId)
                    <form method="POST" action="{{ route('admin.connectors.xero.app.destroy') }}" class="mt-2 text-right"
                          onsubmit="return confirm('Remove the saved Xero app keys?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-rose-600 hover:text-rose-800">Remove saved keys</button>
                    </form>
                @endif
            @else
                <p class="mt-3 text-xs text-slate-500">Only a Full Admin can change the Xero app keys.</p>
            @endif
        </x-ui.card>
    </div>
</x-app-layout>
