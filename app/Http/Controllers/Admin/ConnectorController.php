<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportSource;
use App\Services\Connectors\ConnectorRegistry;
use App\Services\Connectors\Xero\XeroClient;
use Illuminate\View\View;

class ConnectorController extends Controller
{
    public function index(ConnectorRegistry $registry, XeroClient $xero): View
    {
        $xeroSources = ImportSource::where('type', 'xero')->get();

        return view('admin.connectors.index', [
            'connectors' => collect($registry->all())->groupBy('category'),
            'xeroConfigured' => $xero->isConfigured(),
            'xeroSources' => $xeroSources,
            'xeroNeedsAttention' => $xeroSources->contains(fn (ImportSource $source) => $source->last_error
                || ($source->config['last_check_ok'] ?? true) === false
                || ! $source->isRunnable()),
        ]);
    }
}
