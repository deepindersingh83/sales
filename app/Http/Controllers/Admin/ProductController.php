<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Per-workspace product catalogue. Products can be tagged and are searchable;
 * imported transactions can reference them by SKU for product-level reporting.
 */
class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()->orderBy('name');

        if ($term = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%");
            });
        }

        return view('admin.products.index', [
            'products' => $query->with('tags')->paginate(25)->withQueryString(),
            'search' => $request->query('q'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $product = Product::create($data['attributes']);
        $product->syncTagNames($data['tags']);

        return redirect()->route('admin.products.index')->with('status', "Product “{$product->name}” added.");
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product);

        $product->update($data['attributes']);
        $product->syncTagNames($data['tags']);

        return redirect()->route('admin.products.index')->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', 'Product removed.');
    }

    /**
     * @return array{attributes: array<string, mixed>, tags: array<int, string>}
     */
    protected function validated(Request $request, ?Product $product = null): array
    {
        $workspaceId = app(WorkspaceContext::class)->id();

        $validated = $request->validate([
            'sku' => [
                'required', 'string', 'max:100',
                Rule::unique('products', 'sku')
                    ->where('workspace_id', $workspaceId)
                    ->ignore($product?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'list_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'active' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'string'],
        ]);

        return [
            'attributes' => [
                'sku' => $validated['sku'],
                'name' => $validated['name'],
                'category' => $validated['category'] ?? null,
                'list_price' => $validated['list_price'] ?? null,
                'currency' => isset($validated['currency']) ? strtoupper($validated['currency']) : null,
                'active' => $request->boolean('active'),
            ],
            'tags' => array_filter(array_map('trim', explode(',', $validated['tags'] ?? ''))),
        ];
    }
}
