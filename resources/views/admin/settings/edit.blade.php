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

                <div class="pt-2 border-t border-slate-100">
                    <h3 class="text-sm font-semibold text-slate-800">White-labelling</h3>
                    <p class="text-xs text-slate-500">Override the product name, accent colour and logo shown to this workspace.</p>
                </div>
                <div>
                    <x-input-label for="brand_name" value="Brand name (optional)" />
                    <x-text-input id="brand_name" name="brand_name" class="mt-1 block w-full" :value="old('brand_name', $workspace->brand_name)" placeholder="{{ config('branding.name') }}" />
                    <x-input-error :messages="$errors->get('brand_name')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="brand_color" value="Accent colour (hex)" />
                    <div class="mt-1 flex items-center gap-2">
                        <input type="color" value="{{ old('brand_color', $workspace->brand_color ?: '#4f46e5') }}" oninput="document.getElementById('brand_color').value = this.value" class="h-9 w-12 rounded border border-slate-300" />
                        <x-text-input id="brand_color" name="brand_color" class="block w-full" :value="old('brand_color', $workspace->brand_color)" placeholder="#4f46e5" />
                    </div>
                    <x-input-error :messages="$errors->get('brand_color')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="logo_url" value="Logo URL (optional)" />
                    <x-text-input id="logo_url" name="logo_url" type="url" class="mt-1 block w-full" :value="old('logo_url', $workspace->logo_url)" placeholder="https://…/logo.png" />
                    <x-input-error :messages="$errors->get('logo_url')" class="mt-2" />
                </div>

                <div class="flex justify-end">
                    <x-ui.button>Save settings</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card>
            <h2 class="text-sm font-semibold text-slate-800">REST API</h2>
            <p class="mt-1 text-xs text-slate-500">
                Authenticate with <code>Authorization: Bearer &lt;token&gt;</code>.
                Ingest: <code>POST {{ url('/api/v1/transactions') }}</code> ·
                Payouts feed: <code>GET {{ url('/api/v1/payouts') }}</code>
            </p>
            @if (session('api_token'))
                <div class="mt-3 rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm">
                    <div class="text-emerald-800 font-medium">New token (shown once):</div>
                    <code class="break-all text-emerald-900">{{ session('api_token') }}</code>
                </div>
            @endif
            <form method="POST" action="{{ route('admin.settings.api-token') }}" class="mt-3">
                @csrf
                <x-ui.button variant="secondary">Generate / regenerate token</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-app-layout>
