<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DoublePaymentDetector;
use App\Services\Reporting\Asc606Report;
use App\Services\Reporting\ReportBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse as BaseStreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportBuilder $reports) {}

    public function doublePayments(DoublePaymentDetector $detector): View
    {
        return view('admin.reports.double_payments', ['suspects' => $detector->suspects()]);
    }

    public function asc606(Request $request, Asc606Report $report): View
    {
        $months = (int) $request->integer('months', 12);
        $schedule = $report->schedule($months);

        return view('admin.reports.asc606', ['schedule' => $schedule, 'months' => $schedule['months']]);
    }

    /** Visual analytics dashboard. */
    public function overview(): View
    {
        return view('admin.reports.overview', [
            'topUsers' => $this->reports->payoutByUser()->take(8),
            'byType' => $this->reports->payoutByType(),
            'byMonth' => $this->reports->payoutByMonth(),
            'totalReleased' => $this->reports->totalReleasedPayout(),
            'totalLiability' => $this->reports->totalLiability(),
        ]);
    }

    public function attainment(Request $request): View|BaseStreamedResponse
    {
        $rows = $this->reports->attainmentByUser();

        if ($request->query('export') === 'csv') {
            return $this->csv('attainment-by-user.csv', ['User', 'Credited', 'Payout'],
                $rows->map(fn ($r) => [$r['user'], $r['credited'], $r['payout']]));
        }

        return view('admin.reports.attainment', ['rows' => $rows]);
    }

    public function liability(Request $request): View|BaseStreamedResponse
    {
        $rows = $this->reports->liabilityByUser();

        if ($request->query('export') === 'csv') {
            return $this->csv('liability-by-user.csv', ['User', 'Liability'],
                $rows->map(fn ($r) => [$r['user'], $r['liability']]));
        }

        return view('admin.reports.liability', ['rows' => $rows, 'total' => $this->reports->totalLiability()]);
    }

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
