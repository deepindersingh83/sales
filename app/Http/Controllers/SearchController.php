<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Global search across the current workspace: transactions, plans, products,
 * tags and (for admins) members. Everything is workspace-scoped by the global
 * scope, so results never cross tenants.
 */
class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $groups = [];

        if ($term !== '') {
            $like = "%{$term}%";

            $groups['Transactions'] = Transaction::query()
                ->where('external_id', 'like', $like)
                ->limit(10)->get()
                ->map(fn (Transaction $t) => [
                    'label' => $t->external_id,
                    'meta' => $t->currency.' '.number_format((float) $t->amount, 2),
                    'url' => route('admin.transactions.index', ['q' => $t->external_id]),
                ]);

            $groups['Plans'] = Plan::query()
                ->where('name', 'like', $like)
                ->limit(10)->get()
                ->map(fn (Plan $p) => [
                    'label' => $p->name,
                    'meta' => ucfirst((string) $p->status),
                    'url' => route('admin.plans.show', $p),
                ]);

            $groups['Products'] = Product::query()
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like))
                ->limit(10)->get()
                ->map(fn (Product $p) => [
                    'label' => $p->name,
                    'meta' => $p->sku,
                    'url' => route('admin.products.index', ['q' => $p->sku]),
                ]);

            $groups['Tags'] = Tag::query()
                ->where('name', 'like', $like)
                ->limit(10)->get()
                ->map(fn (Tag $t) => [
                    'label' => '#'.$t->name,
                    'meta' => '',
                    'url' => route('search.index', ['q' => $t->name]),
                ]);

            if ($request->user()?->currentRole()?->isAdmin()) {
                $workspaceId = app(WorkspaceContext::class)->id();
                $groups['Members'] = Workspace::withoutGlobalScopes()
                    ->findOrFail($workspaceId)
                    ->users()
                    ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
                    ->limit(10)->get()
                    ->map(fn ($u) => [
                        'label' => $u->name,
                        'meta' => $u->email,
                        'url' => route('admin.members.index'),
                    ]);
            }

            $groups = array_filter($groups, fn ($g) => $g->isNotEmpty());
        }

        return view('search.index', ['term' => $term, 'groups' => $groups]);
    }
}
