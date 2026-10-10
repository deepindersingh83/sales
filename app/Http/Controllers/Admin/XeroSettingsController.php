<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\ImportSource;
use App\Models\IntegrationSetting;
use App\Models\Transaction;
use App\Services\Connectors\Xero\XeroClient;
use App\Services\Connectors\Xero\XeroDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The Xero integration page: the workspace's Xero app keys (Full Admin), and
 * each connected organisation's health, sync schedule, connection check and
 * disconnect (writing admins). Connecting itself is XeroConnectionController.
 */
class XeroSettingsController extends Controller
{
    public const SCHEDULES = ['hourly', 'daily', 'weekly', 'manual'];

    public function __construct(
        protected XeroClient $client,
        protected XeroDriver $driver,
    ) {}

    public function show(Request $request): View
    {
        $saved = IntegrationSetting::for('xero');

        return view('admin.connectors.xero', [
            'app' => $this->client->app(),
            'configured' => $this->client->isConfigured(),
            'savedClientId' => $saved['client_id'] ?? null,
            'hasSavedSecret' => filled($saved['client_secret'] ?? null),
            'redirectUri' => $this->client->redirectUri(),
            'sources' => ImportSource::where('type', 'xero')->orderBy('name')->get(),
            'canManage' => (bool) $request->user()->currentRole()?->canWrite(),
            'isFullAdmin' => $request->user()->currentRole() === Role::FullAdmin,
            'schedules' => self::SCHEDULES,
        ]);
    }

    /** Save the workspace's own Xero app keys. A blank secret keeps the saved one. */
    public function updateApp(Request $request): RedirectResponse
    {
        $this->authorizeFullAdmin($request);

        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:255'],
            'scopes' => ['nullable', 'string', 'max:500'],
        ]);

        $saved = IntegrationSetting::for('xero');
        $secret = filled($data['client_secret'] ?? null) ? $data['client_secret'] : ($saved['client_secret'] ?? null);

        if (blank($secret)) {
            return back()->withErrors(['client_secret' => 'Enter the client secret from your Xero app.'])->withInput();
        }

        IntegrationSetting::updateOrCreate(['provider' => 'xero'], ['settings' => [
            'client_id' => trim($data['client_id']),
            'client_secret' => trim($secret),
            'scopes' => trim((string) ($data['scopes'] ?? '')) ?: null,
        ]]);

        return redirect()->route('admin.connectors.xero.show')->with('status', 'Xero app keys saved.');
    }

    /** Forget the saved app keys (falls back to the server's .env keys, if any). */
    public function destroyApp(Request $request): RedirectResponse
    {
        $this->authorizeFullAdmin($request);

        IntegrationSetting::where('provider', 'xero')->delete();

        return redirect()->route('admin.connectors.xero.show')->with('status', 'Saved Xero app keys removed.');
    }

    /** Test the connection now and remember the outcome. */
    public function check(ImportSource $source): RedirectResponse
    {
        $this->authorizeSource($source);

        $result = $this->driver->check($source);

        $source->forceFill(['config' => array_merge($source->fresh()->config ?? [], [
            'last_checked_at' => now()->toIso8601String(),
            'last_check_ok' => $result['ok'],
            'last_check_message' => $result['message'],
        ])])->save();

        return redirect()->route('admin.connectors.xero.show')
            ->with($result['ok'] ? 'status' : 'check_error', "{$source->name}: {$result['message']}");
    }

    /** Change how often the organisation syncs ("manual" = only on demand). */
    public function update(Request $request, ImportSource $source): RedirectResponse
    {
        $this->authorizeSource($source);

        $data = $request->validate(['schedule' => ['required', 'in:'.implode(',', self::SCHEDULES)]]);
        $schedule = $data['schedule'] === 'manual' ? null : $data['schedule'];

        $source->schedule = $schedule;
        $source->next_run_at = $schedule ? $source->computeNextRunAt(now()) : null;
        $source->save();

        return redirect()->route('admin.connectors.xero.show')
            ->with('status', "{$source->name} now syncs ".($schedule ?? 'manually only').'.');
    }

    /** Revoke access at Xero and remove the connection; imported transactions stay. */
    public function destroy(ImportSource $source): RedirectResponse
    {
        $this->authorizeSource($source);

        $this->driver->revoke($source);
        $source->delete();

        return redirect()->route('admin.connectors.xero.show')
            ->with('status', "Disconnected {$source->name}. Transactions already imported are kept.");
    }

    protected function authorizeSource(ImportSource $source): void
    {
        Gate::authorize('import', Transaction::class);
        abort_unless($source->type === 'xero', 404);
    }

    protected function authorizeFullAdmin(Request $request): void
    {
        abort_unless($request->user()->currentRole() === Role::FullAdmin, 403, 'Full Admin only.');
    }
}
