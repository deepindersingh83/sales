<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alias;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AliasController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Alias::class);

        return view('admin.aliases.index', [
            'aliases' => Alias::with('user')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Alias::class);

        return view('admin.aliases.form', [
            'alias' => new Alias(['match_type' => 'exact', 'match_field' => 'rep']),
            'members' => $this->members(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Alias::class);

        Alias::create($this->validated($request));

        return redirect()->route('admin.aliases.index')->with('status', 'Alias created.');
    }

    public function edit(Alias $alias): View
    {
        Gate::authorize('update', $alias);

        return view('admin.aliases.form', [
            'alias' => $alias,
            'members' => $this->members(),
        ]);
    }

    public function update(Request $request, Alias $alias): RedirectResponse
    {
        Gate::authorize('update', $alias);

        $alias->update($this->validated($request));

        return redirect()->route('admin.aliases.index')->with('status', 'Alias updated.');
    }

    public function destroy(Alias $alias): RedirectResponse
    {
        Gate::authorize('delete', $alias);

        $alias->delete();

        return redirect()->route('admin.aliases.index')->with('status', 'Alias deleted.');
    }

    protected function validated(Request $request): array
    {
        // Constrain the credited user to actual members of this workspace.
        $memberIds = $this->members()->pluck('id')->all();

        return $request->validate([
            'user_id' => ['required', 'integer', Rule::in($memberIds)],
            'alias_value' => ['required', 'string', 'max:255'],
            'match_field' => ['required', 'string', 'max:255'],
            'match_type' => ['required', 'in:exact,contains'],
        ]);
    }

    /**
     * Members of the current workspace, for the credit-to dropdown.
     */
    protected function members()
    {
        $workspaceId = app(WorkspaceContext::class)->id();

        return Workspace::withoutGlobalScopes()->find($workspaceId)?->users()->orderBy('name')->get()
            ?? collect();
    }
}
