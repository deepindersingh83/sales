<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\FxRate;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FX rate management (Full Admin). Rates are effective-dated and scoped to the
 * current workspace.
 */
class FxRateController extends Controller
{
    public function index(): View
    {
        $this->authorizeFullAdmin();

        return view('admin.fx.index', [
            'rates' => FxRate::where('workspace_id', $this->workspaceId())
                ->orderByDesc('effective_date')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeFullAdmin();

        $data = $request->validate([
            'base_currency' => ['required', 'string', 'size:3'],
            'quote_currency' => ['required', 'string', 'size:3', 'different:base_currency'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'effective_date' => ['required', 'date'],
        ]);

        FxRate::create([
            'workspace_id' => $this->workspaceId(),
            'base_currency' => strtoupper($data['base_currency']),
            'quote_currency' => strtoupper($data['quote_currency']),
            'rate' => $data['rate'],
            'effective_date' => $data['effective_date'],
        ]);

        return redirect()->route('admin.fx.index')->with('status', 'FX rate saved.');
    }

    public function destroy(FxRate $fxRate): RedirectResponse
    {
        $this->authorizeFullAdmin();
        abort_unless($fxRate->workspace_id === $this->workspaceId(), 404);

        $fxRate->delete();

        return redirect()->route('admin.fx.index')->with('status', 'FX rate deleted.');
    }

    protected function authorizeFullAdmin(): void
    {
        abort_unless(request()->user()?->currentRole() === Role::FullAdmin, 403, 'Full Admin only.');
    }

    protected function workspaceId(): int
    {
        return app(WorkspaceContext::class)->id();
    }
}
