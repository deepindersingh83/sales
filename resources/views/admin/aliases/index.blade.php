<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('Crediting aliases') }}</h2>
            @can('create', App\Models\Alias::class)
                <a href="{{ route('admin.aliases.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                    {{ __('New alias') }}
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8 max-w-5xl mx-auto sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <p class="mb-4 text-sm text-gray-600">
            An alias is a keyword matched against a field on incoming transactions to decide who gets credited.
        </p>

        <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
            @if ($aliases->isEmpty())
                <p class="p-6 text-gray-500">No aliases yet.</p>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <th class="px-6 py-3">Credited user</th>
                            <th class="px-6 py-3">Match field</th>
                            <th class="px-6 py-3">Match</th>
                            <th class="px-6 py-3">Value</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-sm">
                        @foreach ($aliases as $alias)
                            <tr>
                                <td class="px-6 py-4 font-medium text-gray-900">{{ $alias->user?->name ?? '—' }}</td>
                                <td class="px-6 py-4 font-mono text-gray-500">{{ $alias->match_field }}</td>
                                <td class="px-6 py-4 text-gray-500">{{ $alias->match_type }}</td>
                                <td class="px-6 py-4 text-gray-700">{{ $alias->alias_value }}</td>
                                <td class="px-6 py-4 text-right space-x-3">
                                    @can('update', $alias)
                                        <a href="{{ route('admin.aliases.edit', $alias) }}" class="text-indigo-600 hover:text-indigo-900">Edit</a>
                                    @endcan
                                    @can('delete', $alias)
                                        <form method="POST" action="{{ route('admin.aliases.destroy', $alias) }}" class="inline"
                                              onsubmit="return confirm('Delete this alias?')">
                                            @csrf @method('DELETE')
                                            <button class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-layout>
