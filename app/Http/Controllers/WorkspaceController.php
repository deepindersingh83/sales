<?php

namespace App\Http\Controllers;

use App\Actions\ProvisionWorkspace;
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
    /** Switch the active workspace (must be a member). */
    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($request->user()->belongsToWorkspace($workspace), 403);

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
