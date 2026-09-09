<x-app-layout>
    <x-slot name="header">
        <x-ui.page-header title="Products" subtitle="Your catalogue — SKUs, pricing and tags" />
    </x-slot>

    <div class="p-4 sm:p-6 lg:p-8 space-y-6 max-w-5xl">
        @if (session('status'))
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-4 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        {{-- Add a product --}}
        <x-ui.card>
            <h2 class="text-sm font-semibold text-slate-800 mb-3">Add a product</h2>
            <form method="POST" action="{{ route('admin.products.store') }}" class="grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
                @csrf
                <div class="sm:col-span-1">
                    <x-input-label for="sku" value="SKU" />
                    <x-text-input id="sku" name="sku" class="mt-1 block w-full" :value="old('sku')" required />
                    <x-input-error :messages="$errors->get('sku')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div class="sm:col-span-1">
                    <x-input-label for="category" value="Category" />
                    <x-text-input id="category" name="category" class="mt-1 block w-full" :value="old('category')" />
                </div>
                <div class="sm:col-span-1">
                    <x-input-label for="list_price" value="List price" />
                    <x-text-input id="list_price" name="list_price" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('list_price')" />
                </div>
                <div class="sm:col-span-1">
                    <x-input-label for="currency" value="Currency" />
                    <x-text-input id="currency" name="currency" maxlength="3" class="mt-1 block w-full uppercase" :value="old('currency', 'USD')" />
                </div>
                <div class="sm:col-span-5">
                    <x-input-label for="tags" value="Tags (comma-separated)" />
                    <x-text-input id="tags" name="tags" class="mt-1 block w-full" :value="old('tags')" placeholder="enterprise, new-logo" />
                </div>
                <div class="sm:col-span-1 flex items-center gap-2 pb-2">
                    <input type="checkbox" id="active" name="active" value="1" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" />
                    <label for="active" class="text-sm text-slate-600">Active</label>
                </div>
                <div class="sm:col-span-6 flex justify-end">
                    <x-ui.button>Add product</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        {{-- Filter --}}
        <form method="GET" action="{{ route('admin.products.index') }}" class="flex gap-2">
            <x-text-input name="q" class="block w-full max-w-sm" :value="$search" placeholder="Search products…" />
            <x-ui.button variant="secondary">Search</x-ui.button>
        </form>

        <x-ui.card padding="p-0">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                        <th class="px-6 py-3">SKU</th><th class="px-6 py-3">Name</th>
                        <th class="px-6 py-3">Category</th><th class="px-6 py-3 text-right">List price</th>
                        <th class="px-6 py-3">Tags</th><th class="px-6 py-3">Status</th><th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                        <tr>
                            <td class="px-6 py-3 font-mono text-slate-600">{{ $product->sku }}</td>
                            <td class="px-6 py-3 text-slate-800">{{ $product->name }}</td>
                            <td class="px-6 py-3 text-slate-500">{{ $product->category ?? '—' }}</td>
                            <td class="px-6 py-3 text-right text-slate-700">{{ $product->list_price !== null ? ($product->currency ?? '').' '.number_format((float) $product->list_price, 2) : '—' }}</td>
                            <td class="px-6 py-3">
                                @foreach ($product->tags as $tag)
                                    <span class="inline-block rounded-full bg-slate-100 text-slate-600 px-2 py-0.5 text-xs">#{{ $tag->name }}</span>
                                @endforeach
                            </td>
                            <td class="px-6 py-3">
                                @if ($product->active)
                                    <span class="inline-block rounded-full bg-emerald-50 text-emerald-700 px-2 py-0.5 text-xs">Active</span>
                                @else
                                    <span class="inline-block rounded-full bg-slate-100 text-slate-500 px-2 py-0.5 text-xs">Inactive</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 text-right">
                                <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Remove this product?')">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-600 hover:text-rose-800 text-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-8 text-center text-slate-500">No products yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.card>

        <div>{{ $products->links() }}</div>
    </div>
</x-app-layout>
