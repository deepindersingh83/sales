<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Team" subtitle="Members and their roles in this workspace" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-5xl">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-lg bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        {{-- Add member --}}
        <x-ui.card>
            <h2 class="text-sm font-semibold text-slate-800 mb-4">Add a member</h2>
            <form method="POST" action="{{ route('admin.members.store') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                @csrf
                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                </div>
                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                </div>
                <div>
                    <x-input-label for="role" value="Role" />
                    <select id="role" name="role" class="mt-1 block w-full border-slate-300 rounded-lg shadow-sm text-sm">
                        @foreach ($roles as $r)
                            <option value="{{ $r->value }}" @selected(old('role') === $r->value)>{{ $r->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <x-ui.button>Add member</x-ui.button>
            </form>
            <p class="mt-3 text-xs text-slate-400">New emails get a login with a one-time temporary password (shown once after adding). Existing users are attached to this workspace.</p>
        </x-ui.card>

        {{-- Members --}}
        <x-ui.card padding="p-0">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-3">Member</th>
                        <th class="px-6 py-3">Role &amp; manager</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm">
                    @foreach ($members as $member)
                        <tr>
                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-800">{{ $member->name }}</div>
                                <div class="text-xs text-slate-400">{{ $member->email }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <form method="POST" action="{{ route('admin.members.update', $member) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf @method('PUT')
                                    <select name="role" onchange="this.form.submit()" class="border-slate-300 rounded-lg shadow-sm text-sm py-1">
                                        @foreach ($roles as $r)
                                            <option value="{{ $r->value }}" @selected($member->pivot->role === $r->value)>{{ $r->label() }}</option>
                                        @endforeach
                                    </select>
                                    <select name="manager_id" onchange="this.form.submit()" class="border-slate-300 rounded-lg shadow-sm text-sm py-1 text-slate-500">
                                        <option value="">— no manager —</option>
                                        @foreach ($members as $m)
                                            @if ($m->id !== $member->id)
                                                <option value="{{ $m->id }}" @selected((int) $member->pivot->manager_id === $m->id)>Reports to {{ $m->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <form method="POST" action="{{ route('admin.members.destroy', $member) }}" onsubmit="return confirm('Remove this member from the workspace?')">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-600 hover:text-rose-800 text-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    </div>
</x-app-layout>
