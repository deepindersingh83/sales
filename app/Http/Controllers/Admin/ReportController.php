<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DoublePaymentDetector;
use App\Services\Reporting\Asc606Report;
use App\Services\Reporting\ReportBuilder;
use App\Services\Reporting\RevenueAnalytics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
    public function overview(RevenueAnalytics $revenue): View
    {
        return view('admin.reports.overview', [
            'revenueKpis' => $revenue->kpis(),
            'revenueByMonth' => $revenue->breakdown('month')->take(-12)->values(),
            'distribution' => $this->reports->attainmentDistribution(),
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

        $label = ReportBuilder::CREDITING_DIMENSIONS[$field] ?? ucfirst($field);
        $rows = $this->reports->creditingBy($field);

        if ($request->query('export') === 'csv') {
            return $this->csv("crediting-by-{$field}.csv", [$label, 'Total credited', 'Transactions'],
                $rows->map(fn ($r) => [$r['key'], $r['total'], $r['count']]));
        }

        return view('admin.reports.table', [
            'title' => "Crediting by {$label}",
            'subtitle' => 'Released credited amount',
            'columns' => ['key' => [$label, 'text'], 'count' => ['Transactions', 'number'], 'total' => ['Total credited', 'money']],
            'rows' => $rows,
            'exportUrl' => route('admin.reports.crediting', ['field' => $field, 'export' => 'csv']),
            'filters' => 'admin.reports.partials.crediting-filter',
            'field' => $field,
            'options' => collect($options)->mapWithKeys(fn ($o) => [$o => ReportBuilder::CREDITING_DIMENSIONS[$o] ?? $o]),
        ]);
    }

    /** Transactions no run has credited — usually a missing alias. */
    public function uncredited(Request $request): View|BaseStreamedResponse
    {
        $rows = $this->reports->uncreditedTransactions()->map(fn ($t) => [
            'external_id' => $t->external_id,
            'source' => $t->source_system,
            'date' => $t->transaction_date?->toDateString(),
            'customer' => data_get($t->raw_data, 'customer'),
            'amount' => (float) $t->amount,
            'currency' => $t->currency,
        ]);

        if ($request->query('export') === 'csv') {
            return $this->csv('uncredited-transactions.csv', ['External ID', 'Source', 'Date', 'Customer', 'Amount', 'Currency'],
                $rows->map(fn ($r) => array_values($r)));
        }

        return $this->table('Uncredited transactions', 'Included transactions no calculation has credited to a rep', [
            'external_id' => ['External ID', 'text'], 'source' => ['Source', 'text'], 'date' => ['Date', 'text'],
            'customer' => ['Customer', 'text'], 'amount' => ['Amount', 'money'], 'currency' => ['Currency', 'text'],
        ], $rows, 'admin.reports.uncredited');
    }

    public function payoutByType(Request $request): View|BaseStreamedResponse
    {
        $rows = $this->reports->payoutByType();

        if ($request->query('export') === 'csv') {
            return $this->csv('payout-by-type.csv', ['Reward type', 'Total'], $rows->map(fn ($r) => [$r['type'], $r['total']]));
        }

        return $this->table('Payout by reward type', 'Released payout per reward type',
            ['type' => ['Reward type', 'text'], 'total' => ['Total', 'money']], $rows, 'admin.reports.payout-by-type');
    }

    public function payoutByMonth(Request $request): View|BaseStreamedResponse
    {
        $rows = $this->reports->payoutByMonth();

        if ($request->query('export') === 'csv') {
            return $this->csv('payout-by-month.csv', ['Month', 'Total'], $rows->map(fn ($r) => [$r['month'], $r['total']]));
        }

        return $this->table('Payout by month', 'Released payout per calendar month',
            ['month' => ['Month', 'text'], 'total' => ['Total', 'money']], $rows, 'admin.reports.payout-by-month');
    }

    public function quotaAttainment(Request $request): View|BaseStreamedResponse
    {
        $rows = $this->reports->quotaAttainment();

        if ($request->query('export') === 'csv') {
            return $this->csv('quota-attainment.csv', ['User', 'Plan', 'Credited', 'Quota', 'Attainment %'],
                $rows->map(fn ($r) => [$r['user'], $r['plan'], $r['credited'], $r['quota'], $r['attainment']]));
        }

        return $this->table('Quota attainment', 'Released credited amount vs. plan quota, per rep', [
            'user' => ['User', 'text'], 'plan' => ['Plan', 'text'], 'credited' => ['Credited', 'money'],
            'quota' => ['Quota', 'money'], 'attainment' => ['Attainment', 'percent'],
        ], $rows, 'admin.reports.quota-attainment');
    }

    public function attainmentByPlan(Request $request): View|BaseStreamedResponse
    {
        $rows = $this->reports->attainmentByPlan();

        if ($request->query('export') === 'csv') {
            return $this->csv('attainment-by-plan.csv', ['Plan', 'Reps', 'Credited', 'Payout', 'Avg attainment %'],
                $rows->map(fn ($r) => [$r['plan'], $r['reps'], $r['credited'], $r['payout'], $r['avg_attainment']]));
        }

        return $this->table('Attainment by plan', 'Credited, payout and average quota attainment per plan', [
            'plan' => ['Plan', 'text'], 'reps' => ['Reps', 'number'], 'credited' => ['Credited', 'money'],
            'payout' => ['Payout', 'money'], 'avg_attainment' => ['Avg attainment', 'percent'],
        ], $rows, 'admin.reports.attainment-by-plan');
    }

    public function attainmentByManager(Request $request): View|BaseStreamedResponse
    {
        $rows = $this->reports->attainmentByManager();

        if ($request->query('export') === 'csv') {
            return $this->csv('attainment-by-team.csv', ['Manager', 'Reps', 'Credited', 'Payout'],
                $rows->map(fn ($r) => [$r['manager'], $r['reps'], $r['credited'], $r['payout']]));
        }

        return $this->table('Attainment by team', "Each manager's direct reports, rolled up", [
            'manager' => ['Manager', 'text'], 'reps' => ['Reps', 'number'],
            'credited' => ['Credited', 'money'], 'payout' => ['Payout', 'money'],
        ], $rows, 'admin.reports.attainment-by-team');
    }

    public function attainmentDistribution(Request $request): View|BaseStreamedResponse
    {
        $rows = $this->reports->attainmentDistribution();

        if ($request->query('export') === 'csv') {
            return $this->csv('attainment-distribution.csv', ['Band', 'Reps'], $rows->map(fn ($r) => [$r['band'], $r['count']]));
        }

        return $this->table('Attainment distribution', 'Rep/plan pairs per quota-attainment band',
            ['band' => ['Attainment band', 'text'], 'count' => ['Reps', 'number']], $rows, 'admin.reports.attainment-distribution');
    }

    /** Revenue analytics across every imported transaction, by one of 10 dimensions. */
    public function revenue(Request $request, RevenueAnalytics $revenue): View|BaseStreamedResponse
    {
        $data = $request->validate([
            'dimension' => ['nullable', 'in:'.implode(',', array_keys(RevenueAnalytics::DIMENSIONS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $dimension = $data['dimension'] ?? 'month';
        $from = isset($data['from']) ? Carbon::parse($data['from'])->startOfDay() : null;
        $to = isset($data['to']) ? Carbon::parse($data['to'])->startOfDay() : null;
        $rows = $revenue->breakdown($dimension, $from, $to);
        $label = RevenueAnalytics::DIMENSIONS[$dimension];

        if ($request->query('export') === 'csv') {
            return $this->csv("revenue-by-{$dimension}.csv", [$label, 'Revenue', 'Profit', 'Deals', 'Share %'],
                $rows->map(fn ($r) => [$r['key'], $r['revenue'], $r['profit'], $r['deals'], $r['share']]));
        }

        return view('admin.reports.revenue', [
            'kpis' => $revenue->kpis($from, $to),
            'rows' => $rows,
            'dimension' => $dimension,
            'label' => $label,
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
        ]);
    }

    /**
     * Render the generic table report.
     *
     * @param  array<string, array{0:string, 1:string}>  $columns
     */
    protected function table(string $title, string $subtitle, array $columns, iterable $rows, string $route): View
    {
        return view('admin.reports.table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'columns' => $columns,
            'rows' => $rows,
            'exportUrl' => route($route, ['export' => 'csv']),
        ]);
    }

    /**
     * Stream a CSV download.
     */
    protected function csv(string $filename, array $headers, iterable $rows): BaseStreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens non-ASCII names correctly
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
