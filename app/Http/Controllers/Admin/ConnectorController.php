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
        return view('admin.connectors.index', [
            'connectors' => collect($registry->all())->groupBy('category'),
            'xeroConfigured' => $xero->isConfigured(),
            'xeroSources' => ImportSource::where('type', 'xero')->get(),
        ]);
    }
}
