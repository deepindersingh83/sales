<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Connectors\ConnectorRegistry;
use Illuminate\View\View;

class ConnectorController extends Controller
{
    public function index(ConnectorRegistry $registry): View
    {
        return view('admin.connectors.index', [
            'connectors' => collect($registry->all())->groupBy('category'),
        ]);
    }
}
