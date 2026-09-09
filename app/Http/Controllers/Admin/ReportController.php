<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reporting\ReportBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse as BaseStreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportBuilder $reports) {}

    public function index(): View
    {
        return view('admin.reports.index', [
            'hasData' => $this->reports->hasReleasedData(),
        ]);
    }

    public function payoutByUser(Request $request): View|BaseStreamedResponse
    {
        $rows = $this->reports->payoutByUser();

        if ($request->query('export') === 'csv') {
            return $this->csv('payout-by-user.csv', ['User', 'Total', 'Currency'],
                $rows->map(fn ($r) => [$r['user'], $r['total'], $r['currency']]));
        }

        return view('admin.reports.payout_by_user', ['rows' => $rows]);
    }

    public function payoutByPlan(Request $request): View|BaseStreamedResponse
    {
        $rows = $this->reports->payoutByPlan();

        if ($request->query('export') === 'csv') {
            return $this->csv('payout-by-plan.csv', ['Plan', 'Total', 'Currency'],
                $rows->map(fn ($r) => [$r['plan'], $r['total'], $r['currency']]));
        }

        return view('admin.reports.payout_by_plan', ['rows' => $rows]);
    }

    public function crediting(Request $request): View|BaseStreamedResponse
    {
        $options = $this->reports->creditingFieldOptions();
        $field = $request->query('field');
        if (! in_array($field, $options, true)) {
            $field = $options[0];
        }

        $rows = $this->reports->creditingByField($field);

        if ($request->query('export') === 'csv') {
            return $this->csv("crediting-by-{$field}.csv", [ucfirst($field), 'Total credited', 'Transactions'],
                $rows->map(fn ($r) => [$r['key'], $r['total'], $r['count']]));
        }

        return view('admin.reports.crediting', [
            'rows' => $rows,
            'field' => $field,
            'options' => $options,
        ]);
    }

    /**
     * Stream a CSV download.
     */
    protected function csv(string $filename, array $headers, iterable $rows): BaseStreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
