<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Workspace-level settings (Full Admin): name and base currency.
 */
class WorkspaceSettingsController extends Controller
{
    public function edit(): View
    {
        $this->authorizeFullAdmin();

        return view('admin.settings.edit', ['workspace' => $this->workspace()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeFullAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'base_currency' => ['required', 'string', 'size:3'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'brand_color' => ['nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'logo_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $this->workspace()->update([
            'name' => $data['name'],
            'base_currency' => strtoupper($data['base_currency']),
            'brand_name' => $data['brand_name'] ?? null,
            'brand_color' => $data['brand_color'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
        ]);

        return redirect()->route('admin.settings.edit')->with('status', 'Settings saved.');
    }

    public function regenerateToken(): RedirectResponse
    {
        $this->authorizeFullAdmin();

        $token = $this->workspace()->regenerateApiToken();

        return redirect()->route('admin.settings.edit')
            ->with('status', 'New API token generated — copy it now, it is shown only once.')
            ->with('api_token', $token);
    }

    protected function authorizeFullAdmin(): void
    {
        abort_unless(request()->user()?->currentRole() === Role::FullAdmin, 403, 'Full Admin only.');
    }

    protected function workspace(): Workspace
    {
        return Workspace::withoutGlobalScopes()->findOrFail(app(WorkspaceContext::class)->id());
    }
}
