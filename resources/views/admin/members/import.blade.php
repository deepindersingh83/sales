<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Bulk import members" subtitle="Onboard your team from a CSV">
            <x-slot name="actions">
                <x-ui.button href="{{ route('admin.members.index') }}" variant="secondary">Back to team</x-ui.button>
            </x-slot>
        </x-ui.page-header>
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-2xl space-y-6">
        <x-input-error :messages="$errors->get('file')" class="mb-2" />

        <x-ui.card>
            <form method="POST" action="{{ route('admin.members.import.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="file" value="CSV file" />
                    <input id="file" name="file" type="file" accept=".csv,text/csv" required
                        class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-brand-700 hover:file:bg-brand-100" />
                </div>
                <div class="flex justify-end">
                    <x-ui.button>Import members</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <x-ui.card>
            <h2 class="text-sm font-semibold text-slate-800">Expected columns</h2>
            <p class="mt-1 text-xs text-slate-500">A header row is required. Recognised columns (case-insensitive):</p>
            <ul class="mt-2 text-sm text-slate-600 space-y-1">
                <li><code>name</code> — full name</li>
                <li><code>email</code> — required, the login &amp; unique key</li>
                <li><code>role</code> — full_admin, plan_admin, limited_admin or participant (defaults to participant)</li>
                <li><code>manager_email</code> — optional; links reporting lines</li>
                <li><code>salary</code> — optional base salary</li>
            </ul>
            <pre class="mt-3 rounded-lg bg-slate-900 text-slate-100 text-xs p-3 overflow-x-auto">name,email,role,manager_email,salary
Mia Chen,mia@acme.com,plan_admin,,120000
Rick Ford,rick@acme.com,participant,mia@acme.com,80000</pre>
        </x-ui.card>
    </div>
</x-app-layout>
