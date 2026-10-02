<?php

namespace App\Http\Controllers;

use App\Actions\ProvisionWorkspace;
use App\Enums\Role;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets a user who belongs to several companies switch between them, and create
 * a new company (workspace) that they own as Full Admin.
 */
class WorkspaceController extends Controller
{
    /**
     * Switch the active workspace. Members only — except a platform super
     * admin, who joins any workspace as its Full Admin on first entry.
     */
    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();

        if (! $user->belongsToWorkspace($workspace)) {
            abort_unless($user->isSuperAdmin(), 403);
            $workspace->users()->attach($user->id, ['role' => Role::FullAdmin->value]);
        }

        $request->session()->put('current_workspace_id', $workspace->id);

        return redirect()->route('dashboard')->with('status', "Switched to {$workspace->name}.");
    }

    public function create(): View
    {
        return view('workspaces.create');
    }

    public function store(Request $request, ProvisionWorkspace $provision): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_currency' => ['nullable', 'string', 'size:3'],
        ]);

        $workspace = $provision->handle(
            $request->user(),
            $data['name'],
            strtoupper($data['base_currency'] ?? 'USD'),
        );

        $request->session()->put('current_workspace_id', $workspace->id);

        return redirect()->route('dashboard')->with('status', "Company “{$workspace->name}” created.");
    }
}
