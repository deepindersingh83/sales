<?php

namespace App\Http\Middleware;

use App\Models\ImportSource;
use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audits admin actions whose controllers do not call ActivityLogger
 * themselves — the Xero integration, which is maintained separately.
 *
 * An entry is written only when the action succeeded: the response is not an
 * error and the request flashed a "status" message without flashing
 * validation errors. That flashed message becomes the description. Request
 * input is never recorded (it carries the Xero client secret), only the
 * allow-listed fields below.
 */
class RecordRouteActivity
{
    /**
     * Route name => [action, input fields safe to record].
     *
     * @var array<string, array{0: string, 1: array<int, string>}>
     */
    public const ROUTES = [
        'admin.connectors.xero.app.update' => ['xero.app_keys_saved', []],
        'admin.connectors.xero.app.destroy' => ['xero.app_keys_removed', []],
        'admin.connectors.xero.callback' => ['xero.connected', []],
        'admin.connectors.xero.update' => ['xero.schedule_changed', ['schedule']],
        'admin.connectors.xero.destroy' => ['xero.disconnected', []],
    ];

    public function __construct(protected ActivityLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $entry = self::ROUTES[$request->route()?->getName() ?? ''] ?? null;

        if ($entry !== null && $response->getStatusCode() < 400 && $request->hasSession()) {
            $this->record($request, $entry[0], $entry[1]);
        }

        return $response;
    }

    /**
     * @param  array<int, string>  $fields
     */
    protected function record(Request $request, string $action, array $fields): void
    {
        $session = $request->session();
        $flashed = $session->get('_flash.new', []);

        if (! in_array('status', $flashed, true) || in_array('errors', $flashed, true)) {
            return;
        }

        $source = $request->route('source');

        $this->logger->log(
            $action,
            (string) $session->get('status'),
            $source instanceof ImportSource ? $source : null,
            $request->only($fields),
        );
    }
}
