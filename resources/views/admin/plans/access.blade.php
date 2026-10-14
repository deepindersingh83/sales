<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Plan access" :subtitle="$plan->name" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 max-w-3xl">
        <form method="POST" action="{{ route('admin.plans.access.update', $plan) }}" class="space-y-6">
            @csrf @method('PUT')

            <x-ui.card>
                <h2 class="text-sm font-semibold text-slate-800">Plan Admins</h2>
                <p class="text-xs text-slate-500 mb-3">Plan Admins may edit and run only the plans assigned to them.</p>
                @forelse ($planAdmins as $m)
                    <label class="flex items-center gap-2 py-1.5 text-sm">
                        <input type="checkbox" name="plan_admin_ids[]" value="{{ $m->id }}" @checked(in_array($m->id, $assignedIds)) class="rounded border-slate-300 text-brand-600">
                        <span class="text-slate-700">{{ $m->name }} <span class="text-slate-400">({{ $m->email }})</span></span>
                    </label>
                @empty
                    <p class="text-sm text-slate-500">No Plan Admins in this workspace. Add members with the Plan Admin role on the Team page.</p>
                @endforelse
            </x-ui.card>

            <x-ui.card>
                <h2 class="text-sm font-semibold text-slate-800">Hide from Limited Admins</h2>
                <p class="text-xs text-slate-500 mb-3">Limited Admins are read-only. Tick anyone this plan should be hidden from.</p>
                @forelse ($limitedAdmins as $m)
                    <label class="flex items-center gap-2 py-1.5 text-sm">
                        <input type="checkbox" name="hidden_user_ids[]" value="{{ $m->id }}" @checked(in_array($m->id, $hiddenIds)) class="rounded border-slate-300 text-brand-600">
                        <span class="text-slate-700">{{ $m->name }} <span class="text-slate-400">({{ $m->email }})</span></span>
                    </label>
                @empty
                    <p class="text-sm text-slate-500">No Limited Admins in this workspace.</p>
                @endforelse
            </x-ui.card>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('admin.plans.show', $plan) }}" class="text-sm text-slate-600 hover:text-slate-900">Cancel</a>
                <x-ui.button>Save access</x-ui.button>
            </div>
        </form>
    </div>
</x-app-layout>
